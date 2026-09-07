<?php

function panel_support_unanswered_statuses(): array
{
    return ['Unseen', 'Customerresponse', 'Pending'];
}

function panel_support_conversation_statuses(): array
{
    return support_conversation_statuses();
}

function panel_support_status_map(): array
{
    return [
        'Unseen' => ['tag-warn', 'Unanswered'],
        'Customerresponse' => ['tag-warn', 'New user reply'],
        'Pending' => ['tag-warn', 'Pending'],
        'Answered' => ['tag-ok', 'Answered'],
        'close' => ['tag-plain', 'Closed'],
        'flagged' => ['tag-flag', 'Flagged'],
        'کمپین' => ['tag-info', 'Campaign'],
    ];
}

function panel_support_status_info(string $status): array
{
    return panel_support_status_map()[$status] ?? ['tag-plain', $status ?: 'Unknown'];
}

function panel_support_chat_status_from_messages(array $messages): string
{
    return support_conversation_status_from_messages($messages);
}

/**
 * Latest visible preview for a support inbox row (admin reply wins when it is the last activity).
 * @return array{text:string,from:string,time:string}
 */
function panel_support_preview_message(array $item): array
{
    $userText = trim((string) ($item['text'] ?? ''));
    $adminText = trim((string) ($item['result'] ?? ''));
    $status = (string) ($item['status'] ?? '');
    $answeredAt = trim((string) ($item['answered_at'] ?? ''));
    $time = (string) ($item['time'] ?? '');

    if ($adminText !== '' && ($userText === '' || in_array($status, ['Answered', 'close'], true))) {
        return [
            'text' => $adminText,
            'from' => 'admin',
            'time' => $answeredAt !== '' ? $answeredAt : $time,
        ];
    }

    if ($userText !== '') {
        return [
            'text' => $userText,
            'from' => 'user',
            'time' => $time,
        ];
    }

    if ($adminText !== '') {
        return [
            'text' => $adminText,
            'from' => 'admin',
            'time' => $answeredAt !== '' ? $answeredAt : $time,
        ];
    }

    return [
        'text' => '📎 Attachment',
        'from' => 'user',
        'time' => $time,
    ];
}

function panel_support_unanswered_count(PDO $pdo): int
{
    try {
        if (!support_ensure_conversation_table($pdo)) {
            return 0;
        }
        return db_count($pdo, "SELECT COUNT(*) FROM support_conversation WHERE status = 'Unseen'");
    } catch (Throwable $e) {
        return 0;
    }
}

function panel_support_status_count(PDO $pdo, string $status): int
{
    try {
        if (!support_ensure_conversation_table($pdo)) {
            return 0;
        }
        return db_count($pdo, 'SELECT COUNT(*) FROM support_conversation WHERE status = ?', [$status]);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @return array{ok: bool, msg: string, media?: array}
 */
function panel_support_send_reply(array $ticket, string $reply, ?array $upload = null): array
{
    $botapi = dirname(__DIR__, 2) . '/botapi.php';
    if (!is_file($botapi)) {
        return ['ok' => false, 'msg' => 'Bot connection file not found.'];
    }

    require_once $botapi;
    if (!function_exists('sendmessage')) {
        return ['ok' => false, 'msg' => 'Sending messages through the bot is not available.'];
    }

    $tracking = (string) ($ticket['Tracking'] ?? '');
    $keyboard = json_encode([
        'inline_keyboard' => [[
            ['text' => '✉️ Reply to message', 'callback_data' => 'Responsesusera_' . $tracking],
        ]],
    ], JSON_UNESCAPED_UNICODE);
    $safeReply = htmlspecialchars($reply, ENT_QUOTES, 'UTF-8');
    $message = "📩 A message from the admin was sent to you.\n\nMessage:\n" . $safeReply;
    $media = null;

    if ($upload) {
        $methodMap = [
            'photo' => ['sendPhoto', 'photo'],
            'video' => ['sendVideo', 'video'],
            'audio' => ['sendAudio', 'audio'],
            'document' => ['sendDocument', 'document'],
        ];
        [$method, $field] = $methodMap[$upload['type']];
        $caption = $reply !== '' ? $message : '📎 A file from the admin was sent to you.';
        $response = telegram($method, [
            'chat_id' => $ticket['iduser'],
            $field => new CURLFile($upload['path'], $upload['mime'], $upload['name']),
            'caption' => $caption,
            'reply_markup' => $keyboard,
            'parse_mode' => 'HTML',
        ]);
        if (!empty($response['ok'])) {
            $payload = $response['result'] ?? [];
            $photos = $payload['photo'] ?? [];
            $file = $upload['type'] === 'photo' ? (end($photos) ?: []) : ($payload[$upload['type']] ?? []);
            $media = [[
                $upload['type'],
                $file['file_id'] ?? '',
                $file['file_unique_id'] ?? null,
                $file['mime_type'] ?? $upload['mime'],
                $file['file_name'] ?? $upload['name'],
                $file['file_size'] ?? $upload['size'],
            ]];
            if ($media[0][1] === '') {
                return ['ok' => false, 'msg' => 'File was sent but Telegram did not return a file ID.'];
            }
        }
    } else {
        $response = sendmessage($ticket['iduser'], $message, $keyboard, 'HTML');
    }
    if (empty($response['ok'])) {
        return ['ok' => false, 'msg' => 'Could not send the message to the user: ' . ($response['description'] ?? 'Unknown error')];
    }

    return ['ok' => true, 'msg' => 'Reply sent to the user.', 'media' => $media];
}

/**
 * @return array{ok: bool, msg?: string, upload?: array}
 */
function panel_support_prepare_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'upload' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'msg' => 'File upload failed.'];
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > 20 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'File size must be at most 20 MB.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
    $type = str_starts_with($mime, 'image/') ? 'photo'
        : (str_starts_with($mime, 'video/') ? 'video'
        : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'));
    $name = basename((string) ($file['name'] ?? 'attachment'));
    $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?: 'attachment';

    return ['ok' => true, 'upload' => [
        'path' => $file['tmp_name'],
        'name' => mb_substr($name, 0, 200, 'UTF-8'),
        'mime' => $mime,
        'size' => (int) $file['size'],
        'type' => $type,
    ]];
}

function panel_faq_list(PDO $pdo): array
{
    return faq_list($pdo, false);
}

/**
 * @return array{ok:bool,msg:string}
 */
function panel_faq_add(PDO $pdo, string $question, string $answer, int $sortOrder = 0, bool $active = true): array
{
    faq_ensure_schema($pdo);
    $question = trim($question);
    $answer = trim($answer);
    if ($question === '') {
        return ['ok' => false, 'msg' => 'Question is required.'];
    }
    if ($answer === '') {
        return ['ok' => false, 'msg' => 'Answer is required.'];
    }
    if (mb_strlen($question, 'UTF-8') > 255) {
        return ['ok' => false, 'msg' => 'Question must be at most 255 characters.'];
    }
    if (mb_strlen($answer, 'UTF-8') > 3500) {
        return ['ok' => false, 'msg' => 'Answer must be at most 3500 characters.'];
    }
    db_query(
        $pdo,
        'INSERT INTO support_faq (question, answer, sort_order, is_active) VALUES (?,?,?,?)',
        [$question, $answer, $sortOrder, $active ? 1 : 0]
    );
    return ['ok' => true, 'msg' => 'Question added.'];
}

/**
 * @return array{ok:bool,msg:string}
 */
function panel_faq_update(PDO $pdo, int $id, string $question, string $answer, int $sortOrder, bool $active): array
{
    faq_ensure_schema($pdo);
    $question = trim($question);
    $answer = trim($answer);
    if ($id < 1) {
        return ['ok' => false, 'msg' => 'Question not found.'];
    }
    if ($question === '') {
        return ['ok' => false, 'msg' => 'Question is required.'];
    }
    if ($answer === '') {
        return ['ok' => false, 'msg' => 'Answer is required.'];
    }
    if (mb_strlen($question, 'UTF-8') > 255) {
        return ['ok' => false, 'msg' => 'Question must be at most 255 characters.'];
    }
    if (mb_strlen($answer, 'UTF-8') > 3500) {
        return ['ok' => false, 'msg' => 'Answer must be at most 3500 characters.'];
    }
    $row = faq_get($pdo, $id);
    if (!$row) {
        return ['ok' => false, 'msg' => 'Question not found.'];
    }
    db_query(
        $pdo,
        'UPDATE support_faq SET question = ?, answer = ?, sort_order = ?, is_active = ? WHERE id = ?',
        [$question, $answer, $sortOrder, $active ? 1 : 0, $id]
    );
    return ['ok' => true, 'msg' => 'Question updated.'];
}

/**
 * @return array{ok:bool,msg:string}
 */
function panel_faq_delete(PDO $pdo, int $id): array
{
    faq_ensure_schema($pdo);
    $row = faq_get($pdo, $id);
    if (!$row) {
        return ['ok' => false, 'msg' => 'Question not found.'];
    }
    db_query($pdo, 'DELETE FROM support_faq WHERE id = ?', [$id]);
    return ['ok' => true, 'msg' => 'Question deleted.'];
}

function panel_upsert_textbot(PDO $pdo, string $idText, string $text): void
{
    $exists = db_fetch($pdo, 'SELECT id_text FROM textbot WHERE id_text = ?', [$idText]);
    if ($exists) {
        db_query($pdo, 'UPDATE textbot SET text = ? WHERE id_text = ?', [$text, $idText]);
    } else {
        db_query($pdo, 'INSERT INTO textbot (id_text, text) VALUES (?, ?)', [$idText, $text]);
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('textbot');
    }
}
