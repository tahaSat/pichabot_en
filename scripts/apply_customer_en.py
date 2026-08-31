#!/usr/bin/env python3
"""Replace customer-facing Farsi string literals with English. Skip SQL, identifiers, admin reports."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

SKIP_EXACT = {
    "سرویس تست",
    "نام کاربری دلخواه + عدد رندوم",
    "متن دلخواه + عدد ترتیبی",
    "نام کاربری + عدد به ترتیب",
    "آیدی عددی+عدد ترتیبی",
    "متن دلخواه نماینده + عدد ترتیبی",
    "نام کاربری دلخواه",
    "wheelـluck",
    "wheelـluck_price",
    "priceـper_gig",
    "priceـper_day",
    "/^helpctgoryـ(.*)/",
}

ADMIN_MARKERS = (
    "جزئیات ساخت",
    "جزئیات تمدید",
    "جزئیات تغییر",
    "یک پرداخت جدید انجام شده",
    "ادمین عزیز",
    "پشتیبان عزیز",
    "سلام ادمین",
    "در صورت درست بودن رسید",
    "خطا در ساخت لینک",
    "خطا در هنگام ساخت",
    "خطا هنگام تغییر",
    "قصد پرداخت داشت",
    "قصد دریافت اکانت",
    "اطلاعیه کرون",
    "گروه گزارش",
    "آیدی ادمین",
    "خطای ساخت اشتراک",
    "خطای تمدید سرویس",
    "خطای خرید حجم",
    "خطا در ساخت کافنیگ",
    "خطا در ساخت کانفیگ",
    "خطای اعمال شارژ",
    "مبلغ $result به کاربر",
    "مبلغ $result به کاربر",
)

EXACT = {
    "تست": "Test",
    "روز": "days",
    "گیگ": "GB",
    "عادی": "Regular",
    " دیگر": " other",
    "آفلاین": "Offline",
    "آنلاین": "Online",
    "پرداخت": "Pay",
    "نامحدود": "Unlimited",
    "نماینده": "Agent",
    " (تمدید)": " (renewal)",
    "کپی مبلغ": "Copy amount",
    "گیگابایت": "GB",
    "💎 پرداخت": "💎 Pay",
    "متصل نشده": "Not connected",
    "❌حذف دستی": "❌ Manual delete",
    "❌حذف سرویس": "❌ Delete service",
    "❌ حذف سرویس": "❌ Delete service",
    "📌 عضویت مجدد": "📌 Join again",
    "🗂 خرید انبوه": "🗂 Bulk buy",
    "🛍 حجم دلخواه": "🛍 Custom data",
    "کارت یافت نشد": "Card not found",
    "کپی شماره کارت": "Copy card number",
    "❌عدم تایید حذف": "❌ Reject deletion",
    "فایل اشتراک شما": "Your subscription file",
    "⚙️ سرویس دلخواه": "⚙️ Custom service",
    "❌ پنل یافت نشد.": "❌ Panel not found.",
    "📎 فایل پیوست شد": "📎 File attached",
    "📝 تغییر یادداشت": "📝 Edit note",
    "نمایندگی پیشرفته": "Advanced agent",
    "📌 خرید اول کاربر": "📌 First purchase",
    "⚙️ اطلاعات کانفیگ": "⚙️ Config details",
    "💡 روشن کردن اکانت": "💡 Enable account",
    "♻️ اطلاعات بروز شد": "♻️ Details updated",
    "❌ خاموش کردن اکانت": "❌ Disable account",
    "🔴 ارسال نشده است 🔴": "🔴 Not submitted 🔴",
    "📍 انتخاب سرویس فعلی": "📍 Select current service",
    "♻️ بروزرسانی اطلاعات": "♻️ Refresh details",
    "✅ تایید انتقال سرویس": "✅ Confirm service transfer",
    "✅ قوانین را می پذیرم": "✅ I accept the rules",
    "🔗 لینک دانلود برنامه": "🔗 App download link",
    "⚠️ ارسال گزارش اختلال": "⚠️ Report an outage",
    "🔙 بازگشت به منوی اصلی": "🔙 Back to main menu",
    "✅ تایید شده توسط ادمین": "✅ Verified by admin",
    "❌ درخواست برداشت لغو شد.": "❌ Withdrawal request cancelled.",
    "❌ مدت سرویس نامعتبر است.": "❌ Invalid service duration.",
    "📌 سرویس با موفقیت حذف شد": "📌 Service deleted",
    "❌ ثبت درخواست ناموفق بود.": "❌ Could not submit the request.",
    "❌ پیام پشتیبانی یافت نشد.": "❌ Support message not found.",
    "🏠 بازگشت به اطلاعات سرویس": "🏠 Back to service details",
    "پیام با موفقیت ارسال گردید": "Message sent",
    "✅ تایید و فعال کردن کانفیگ": "✅ Confirm and enable config",
    "❌ این دکمه غیرفعال می باشد": "❌ This button is disabled",
    "📌 پیام خود را ارسال نمایید": "📌 Send your message",
    "📌 یک دسته را انتخاب نمایید": "📌 Select a category",
    "✅ پرداخت کردم | ارسال رسید.": "✅ I have paid | Send receipt",
    "❌ این کمپین دیگر فعال نیست.": "❌ This campaign is no longer active.",
    "♻️ در حال ساختن سرویس شما...": "♻️ Creating your service...",
    "✅  درخواست حذف سرویس را دارم": "✅ I want to delete this service",
    "✅ تایید و ارسال گزارش اختلال": "✅ Confirm and send outage report",
    "✅ تایید و غیرفعال کردن کانفیگ": "✅ Confirm and disable config",
    "❌ مدت انتخاب‌شده نامعتبر است.": "❌ The selected duration is invalid.",
    "👤 نام صاحب حساب را وارد کنید:": "👤 Enter the account holder name:",
    "📌شما 1 امتیاز جدید کسب کردید.": "📌 You earned 1 new point.",
    "📌شما 2 امتیاز جدید کسب کردید.": "📌 You earned 2 new points.",
    "🎉 هدیه عضویت برای شما فعال شد!": "🎉 Your signup bonus was activated!",
    "📌 متن پیام خود را ارسال نمایید": "📌 Send your message",
    "🎁 یک کمپین دعوت را انتخاب کنید:": "🎁 Choose an invite campaign:",
    "❌ این بخش در حال غیرفعال می باشد": "❌ This section is currently disabled",
    "❌ نام صاحب حساب خیلی طولانی است.": "❌ Account holder name is too long.",
    "🖼 تصویر رسید خود را ارسال نمایید": "🖼 Send a photo of your receipt",
    "❌ امکان انتقال به پنل وجود ندارد.": "❌ This panel cannot receive a transfer.",
    "📌 دسته بندی خود را انتخاب نمایید!": "📌 Select a category!",
    "✅ ارسال لینک واریز یا تصویر واریزی": "✅ Send the deposit link or receipt photo",
    "❌ خطایی در تغییر لینک رخ داده است.": "❌ An error occurred while changing the link.",
    "❌ موجودی این سرویس به پایان رسیده.": "❌ This service has no remaining data.",
    "❌ نام صاحب حساب را کامل وارد کنید.": "❌ Enter the full account holder name.",
    "💳 شماره کارت ۱۶ رقمی را وارد کنید:": "💳 Enter the 16-digit card number:",
    "📛 شما زیرمجموعه هیچ کاربری نیستید.": "📛 You are not referred by anyone.",
    "❌  فقط مجاز به ارسال یک تصویر هستید": "❌ You may send only one image",
    "❌ این دکمه برای شما غیرفعال می باشد": "❌ This button is disabled for you",
    "❌ زمان کد تخفیف به پایان رسیده است.": "❌ This discount code has expired.",
    "📌 دلیل حذف سرویس خود را ارسال کنید.": "📌 Send the reason for deleting this service.",
    "📛 این بخش درحال حاضر غیرفعال می باشد": "📛 This section is currently disabled",
    "❌ اطلاعات ناقص است. دوباره تلاش کنید.": "❌ Incomplete information. Please try again.",
    "❌ این قابلیت درحال حاضر در دسترس نیست": "❌ This feature is currently unavailable",
    "❌ این قابلیت درحال حاضر دردسترس نیست.": "❌ This feature is currently unavailable.",
    "❌ لطفا مراحل خرید را مجددا انجام دهید": "❌ Please start the purchase again",
    "✏️ کدام مورد را می‌خواهید ویرایش کنید؟": "✏️ What do you want to edit?",
    "❌ سرویس دلخواه برای این پنل فعال نیست.": "❌ Custom service is not enabled for this panel.",
    "❌ پیام توسط ادمین دیگری پاسخ داده شده.": "❌ Another admin already replied to this message.",
    "✅ کانفیگ شما با موفقیت بروزرسانی گردید.": "✅ Your config was updated.",
    "❌ مراحل خرید را مجددا از اول انجام دهید": "❌ Please start the purchase from the beginning",
    "📌 سرویس تست در حال حاضر در دسترس نیست .": "📌 Test service is currently unavailable.",
    "❗️ تراکنش شما توسط ربات تایید گردیده است.": "❗️ This transaction was already confirmed by the bot.",
    "❌ امکان خرید با این کد کد تخفیف وجود ندارد": "❌ This discount code cannot be used for this purchase",
    "📛 بخش دعوت دوستان در حال حاضر غیرفعال است.": "📛 The invite-friends section is currently disabled.",
    "⌛️ مدت زمان تمدید را از دکمه‌ها انتخاب کنید": "⌛️ Choose the renewal duration from the buttons",
    "⌛️ مدت زمان سرویس را از دکمه‌ها انتخاب کنید": "⌛️ Choose the service duration from the buttons",
    "❌ خطایی رخ داده است مراحل را از اول طی کنید": "❌ An error occurred. Please start over.",
    "❌ محصول یافت نشد. خرید را از اول انجام دهید": "❌ Product not found. Please start the purchase again.",
    "📛 در حال حاضر کمپین دعوتی فعالی وجود ندارد.": "📛 There is no active invite campaign right now.",
    "❌ امکان خرید حجم اضافه در این پنل وجود ندارد": "❌ Extra data cannot be purchased on this panel",
    "❌ تمدید خودکار برای سرویس تست در دسترس نیست.": "❌ Auto-renew is not available for test services.",
    "❌ امکان خرید زمان اضافه در این پنل وجود ندارد": "❌ Extra time cannot be purchased on this panel",
    "❌ شما دسترسی پاسخ به پیام پشتیبانی را ندارید.": "❌ You do not have permission to reply to support messages.",
    "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است": "❌ You have reached your location-change limit",
    "❌ خطایی رخ داده است لطفا مراحل مجددا انجام دهید": "❌ An error occurred. Please try the steps again.",
    "❌ دپارتمان پشتیبانی یافت نشد. دوباره تلاش کنید.": "❌ Support department not found. Please try again.",
    "❌ پنل یافت نشد. مراحل خرید را از اول انجام دهید": "❌ Panel not found. Please start the purchase again.",
    "لطفاً مقدار خواسته‌شده را به‌صورت متن ارسال کنید.": "Please send the requested value as text.",
    "❌ ارسال پاسخ با خطا روبه‌رو شد. دوباره تلاش کنید.": "❌ Sending the reply failed. Please try again.",
    "❌ محصول یافت نشد. مراحل خرید را از اول انجام دهید": "❌ Product not found. Please start the purchase again.",
    "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.": "📌 Select a config from the list below.",
    "❌ امکان مشاهده اطلاعات اکانت درحال حاضر وجود ندارد": "❌ Account details cannot be viewed right now",
    "❌ خطایی رخ داده است مراحل خرید را از اول انجام دهید": "❌ An error occurred. Please start the purchase again.",
    "❌ مدت سرویس نامعتبر است. خرید را از اول انجام دهید.": "❌ Invalid service duration. Please start the purchase again.",
    "❌ این محصول / دسته‌بندی برای نمایندگی شما فعال نیست.": "❌ This product/category is not enabled for your agency.",
    "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.": "❌ Purchase failed. Please try again.",
    "❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.": "❌ An error occurred. Please start the renewal again.",
    "❌ شماره کارت نامعتبر است. یک شماره ۱۶ رقمی وارد کنید.": "❌ Invalid card number. Enter a 16-digit number.",
    "📌 بخش پشتیبانی که میخواهید پیام دهید را انتخاب نمایید.": "📌 Choose the support department you want to message.",
    "📌 تصویر واریزی خود یا لینک تراکنش ترون را ارسال نمایید.": "📌 Send your deposit photo or TRON transaction link.",
    "🚀 رسید شما ارسال و پس از بررسی سرویس شما تمدید خواهد شد": "🚀 Your receipt was sent. After review, the service will be renewed",
    "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید": "❌ A renewal error occurred. Please contact support.",
    "🎉 یک نفر با معرفی شما وارد شد! هدیه به حساب شما واریز شد.": "🎉 Someone joined with your invite! The bonus was added to your account.",
    "لطفاً یکی از دکمه‌های تأیید، لغو یا ویرایش را انتخاب کنید.": "Please tap Confirm, Cancel, or Edit.",
    "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.": "❌ Renewal failed. Please try again.",
    "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.": "❌ Could not read config details. Please contact support.",
    "❌ خطایی در ثبت پیام پشتیبانی رخ داد. لطفاً دوباره تلاش کنید.": "❌ Could not save the support message. Please try again.",
    "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.": "❌ This panel is unavailable. Please buy from another panel.",
    "❌ سرویس غیرفعال است و امکان تعویض لینک برای سرویس وجود ندارد.": "❌ This service is disabled, so the link cannot be changed.",
    "❌ مبلغ نامعتبر است. مبلغ را به تومان و به‌صورت عدد وارد کنید.": "❌ Invalid amount. Enter a number in Toman.",
    "🚀 رسید شما ارسال و پس از بررسی به سرویس شما زمان اضافه خواهد شد": "🚀 Your receipt was sent. After review, extra time will be added",
    "🚀 رسید شما ارسال و پس از بررسی  به سرویس شما حجم اضافه خواهد شد.": "🚀 Your receipt was sent. After review, extra data will be added.",
    "❌ خطای داخلی در بازیابی کارت بانکی رخ داد. لطفاً بعداً تلاش کنید.": "❌ Internal error while loading the bank card. Please try later.",
    "❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید": "❌ An error occurred. Please start the purchase or payment again.",
    "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.": "❌ Prices cannot be fetched right now. Please try later.",
    "❌ خطایی در دریافت نتیجه بازی رخ داد. لطفاً بعداً مجدداً تلاش کنید.": "❌ Could not load the game result. Please try later.",
    "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید": "❌ Extra-data purchase failed. Please contact support.",
    "لطفاً مبلغ را وارد کنید یا از دکمه برداشت تمام موجودی استفاده کنید.": "Enter an amount or tap Withdraw full balance.",
    "✅ پیام شما با موفقیت ارسال و پس از بررسی به شما پاسخ داده خواهد شد.": "✅ Your message was sent and will be answered after review.",
    "❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.": "❌ This service has no remaining data. Please buy another service.",
    "❌ متاسفانه این آپشن فقط برای کاربرانی فعال است که از ربات خریدی نداشته باشند.": "❌ This option is only available to users who have not purchased from the bot yet.",
    "❌ کانفیگ شما در وضعیت استفاده نشده است و امکان انتقال موقعیت سرویس وجود ندارد.": "❌ This config has not been used yet, so the location cannot be changed.",
    "✅  پیام شما برای این درخواست با موفقیت ارسال گردید پس از بررسی پاسخ داده خواهد شد.": "✅ Your message for this request was sent and will be answered after review.",
    "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>\nاین هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است.": "<b>⛔ You have already claimed the signup bonus.</b>\nThis bonus can be activated <b>once</b> only.",
    "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.": "❌ This plan cannot be renewed. Please start over and choose another plan.",
    "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید": "❌ You have not connected yet. Connect first, then renew the service.",
    "❌ هنوز به کانفیگ متصل نشده اید و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.": "❌ You have not connected to the config yet, so the status cannot be changed. Connect first, then try again.",
    "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.": "❌ No active bank card was found for this payment method. Please try later or contact support.",
    "❌ این دکمه غیرفعال می باشد": "❌ This button is disabled",
    "💸 درخواست برداشت": "💸 Withdraw request",
    "❌ بستن لیست": "❌ Close list",
    "☎️ ارسال شماره تلفن": "☎️ Send phone number",
    "برای بازگشت روی دکمه زیر کلیک کنید": "Tap the button below to go back",
    "🏠 بازگشت": "🏠 Back",
    "🔙 بازگشت": "🔙 Back",
    "📚 آموزش": "📚 Guide",
    "☎️ پشتیبانی": "☎️ Support",
    "🔐 خرید اشتراک": "🔐 Buy subscription",
    "🎲 گردونه شانس": "🎲 Lucky wheel",
    "🔑 اکانت تست": "🔑 Test account",
    "بررسی عضویت": "Check membership",
    "خوش آمدید": "Welcome",
    "مصرف نشده": "Not used",
    "مخفی ❌": "Hidden ❌",
    "نمایش ✅": "Visible ✅",
    "پنل یافت نشد.": "Panel not found.",
    "🛍خرید اشتراک": "🛍 Buy subscription",
    "ربات در حالت توسعه و بروز رسانی میباشد. لطفا پس از مدتی مجددا تلاش نمایید.": "The bot is in maintenance mode. Please try again later.",
    " پنل انتخابی موجود نیست.": " Selected panel was not found.",
    "پنل انتخابی موجود نیست.": "Selected panel was not found.",
    "پنل انتخابی درحال حاضر فعال نیست": "The selected panel is not active right now",
    "حجم نامعتبر است خرید را از اول انجام دهید": "Invalid data amount. Please start the purchase again.",
    "مدت ماه نامعتبر است خرید را از اول انجام دهید": "Invalid duration. Please start the purchase again.",
    "محصول انتخابی پیدا نشد": "Selected product was not found",
    "موجودی کمتر از قیمت محصول است": "Balance is less than the product price",
    "نام کاربری وجود دارد مراحل را از اول طی کنید": "This username already exists. Please start over.",
    "خطایی در ساخت اشتراک رخ داده است با پشتیبانی در ارتباط باشید": "An error occurred while creating the subscription. Please contact support.",
    "❌ کاربر عزیز مبلغ {$data['amount']} تومان از  موجودی کیف پول تان کسر گردید.": "❌ {$data['amount']} Toman was deducted from your wallet.",
    "💎 کاربر عزیز مبلغ {$data['amount']} تومان به موجودی کیف پول تان اضافه گردید.": "💎 {$data['amount']} Toman was added to your wallet.",
    "✳️ حساب کاربری شما از مسدودی خارج شد ✳️\nاکنون میتوانید از ربات استفاده کنید ✔️": "✳️ Your account was unblocked ✳️\nYou can use the bot now ✔️",
    "💎 کاربر گرامی حساب کاربری شما با موفقیت احراز هویت گردید و هم اکنون می توانیدخرید خود را انجام دهید": "💎 Your account was verified. You can now make a purchase.",
    "❌ خطایی در ساخت اشتراک رخ داده است برای رفع مشکل علت خطا را در گروه گزارش تان بررسی کنید": "❌ An error occurred while creating the subscription. Check the error details in your report group.",
    "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید": "❌ A renewal error occurred. Please contact support.",
    "❌ خطایی در هنگام تمدید رخ داده با پشتیبانی در ارتباط باشید": "❌ A renewal error occurred. Please contact support.",
    "حساب کاربری شما با موفقیت احرازهویت گردید": "Your account was verified",
    "📌 جهت دریافت کانفیگ روی دکمه دریافت کانفیگ کلیک کنید": "📌 Tap Get config to receive your config",
    "❌ نمی‌توانید از لینک دعوت خودتان استفاده کنید.": "❌ You cannot use your own invite link.",
    "❌ این لینک دعوت فقط برای کاربران جدید معتبر است.": "❌ This invite link is only valid for new users.",
    "📚 مشاهده آموزش استفاده ": "📚 View usage guide ",
    "➕ افزودن کارت جدید": "➕ Add a new card",
    "🔙 بازگشت به بررسی": "🔙 Back to review",
    "💳 یک کارت را انتخاب کنید": "💳 Select a card",
    "✅ تأیید": "✅ Confirm",
    "❌ لغو": "❌ Cancel",
    "✏️ ویرایش": "✏️ Edit",
    "💰 مبلغ": "💰 Amount",
    "💳 کارت": "💳 Card",
    "💰 برداشت تمام موجودی": "💰 Withdraw full balance",
    "✅ درخواست تسویه حساب شما با موفقیت پردازش شد.": "✅ Your payout request was processed.",
}

# Longer / templated user strings
EXACT.update({
    "📶 اخرین زمان اتصال شما : $lastonline": "📶 Last connection: $lastonline",
    "✍️ یادداشت کانفیگ : {$nameloc['note']}": "✍️ Config note: {$nameloc['note']}",
    "🥅 امتیاز حساب کاربری شما : {$user['score']}": "🥅 Your points: {$user['score']}",
    "اشتراک شما : <code>$output_config_link</code>": "Your subscription: <code>$output_config_link</code>",
    "🔑 رمز عبور سرویس شما : <code>{$DataUserOut['subscription_url']}</code>": "🔑 Service password: <code>{$DataUserOut['subscription_url']}</code>",
    "تبریک 🎉\n📌 به عنوان هدیه تمدید مبلغ $result تومان حساب شما شارژ گردید": "Congratulations 🎉\n📌 $result Toman was added to your account as a renewal bonus",
    "⭕️ این کد تنها {$SellDiscountlimit['useuser']}  بار قابل استفاده است": "⭕️ This code can only be used {$SellDiscountlimit['useuser']} time(s)",
    "❌ خطا \n💬 مبلغ باید حداقل $minbalance تومان و حداکثر $maxbalance تومان باشد": "❌ Error\n💬 Amount must be at least $minbalance Toman and at most $maxbalance Toman",
    "❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید": "❌ Could not load the details. Please start over.",
    "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance و حداکثر $maxbalance تومان باشد": "❌ Minimum for this method is $mainbalance and maximum is $maxbalance Toman",
    "❌ برای خرید انبوه باید حداقل $PaySetting تومان موجودی داشته باشید.": "❌ Bulk buy requires at least $PaySetting Toman in your wallet.",
    "❌ حجم نامعتبر است.\\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد": "❌ Invalid data amount.\\n🔔 Minimum $mainvolume GB and maximum $maxvolume GB",
    "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalance_fmt و حداکثر $maxbalance_fmt تومان باشد": "❌ Minimum for this method is $mainbalance_fmt and maximum is $maxbalance_fmt Toman",
    "🤩 کد تخفیف شما درست بود و  {$SellDiscountlimit['price']} درصد تخفیف روی فاکتور شما اعمال شد.": "🤩 Discount applied. {$SellDiscountlimit['price']} percent off your invoice.",
    "💸 مبلغ را  به تومان وارد کنید:\n✅  حداقل مبلغ $minbalance حداکثر مبلغ $maxbalance تومان می باشد": "💸 Enter the amount in Toman:\n✅ Minimum $minbalance, maximum $maxbalance Toman",
    "❌ حداقل مبلغ واریزی این روش پرداخت باید $mainbalanceplisio و حداکثر $maxbalanceplisio تومان باشد": "❌ Minimum for this method is $mainbalanceplisio and maximum is $maxbalanceplisio Toman",
    "🎁 مبلغ $addbalancediscount به موجودی شما از طرف زیر مجموعه با شناسه کاربری $from_id اضافه گردید.": "🎁 $addbalancediscount was added to your balance from referral user ID $from_id.",
    "⌛️ مدت زمان تمدید را انتخاب کنید\n📌 هر ماه معادل ۳۰ روز است\n⚠️ فقط گزینه‌های زیر قابل انتخاب هستند": "⌛️ Choose the renewal duration\n📌 Each month equals 30 days\n⚠️ Only the options below can be selected",
    "⌛️ مدت زمان سرویس را انتخاب کنید\n📌 هر ماه معادل ۳۰ روز است\n⚠️ فقط گزینه‌های زیر قابل انتخاب هستند": "⌛️ Choose the service duration\n📌 Each month equals 30 days\n⚠️ Only the options below can be selected",
    "❌ شما بدهی دارید، باید حداقل $balancruser تومان پرداخت کنید.\n         میبغ خود را مجددا ارسال نمایید": "❌ You have a debt. Please pay at least $balancruser Toman.\n         Send the amount again",
    "تعداد افراد در صف درخواست درگاه پرداخت بشدت زیاد است 📊\n\n‼️درحال حاظر از روش پرداخت دیگری استفاده کنید": "The payment gateway queue is very busy 📊\n\n‼️ Please use another payment method for now",
    "💎  کاربر عزیز بدلیل ساخته نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.": "💎 The service could not be created, so $balance Toman was added back to your wallet.",
    "💎  کاربر عزیز بدلیل تمدید نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.": "💎 The service could not be renewed, so $balance Toman was added back to your wallet.",
    "💎 کاربر گرامی مبلغ {$Payment_report['price']} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.": "💎 {$Payment_report['price']} Toman was added to your wallet. Thank you for your payment.",
    "<b>🎉 خوش آمدید!</b>\\n\\nشما با دعوت <b>{$referrer_name}</b> وارد ربات شدید.": "<b>🎉 Welcome!</b>\\n\\nYou joined via <b>{$referrer_name}</b>'s invite.",
    "✅ یک دعوت جدید ثبت شد!\\n\\nکمپین: <b>{$campaign['title']}</b>\\nپیشرفت: {$invite_count} / {$required}": "✅ A new invite was recorded!\\n\\nCampaign: <b>{$campaign['title']}</b>\\nProgress: {$invite_count} / {$required}",
    "❌ درخواست برداشت شما رد شد.\\n\\n✍️ ": "❌ Your withdrawal request was rejected.\\n\\n✍️ ",
    "پنل انتخابی موجود نیست.": "Selected panel was not found.",
})

PHRASES = [
    ("با سلام خدمت شما کاربر گرامی", "Hello"),
    ("کاربر گرامی", ""),
    ("کاربر عزیز", ""),
    ("از انجام تراکنش متشکریم!", "Thank you for your payment!"),
    ("پرداخت موفق", "Payment successful"),
    ("پرداخت ناموفق", "Payment failed"),
    ("ناموفق", "Failed"),
    ("فاکتور پرداخت", "Payment invoice"),
    ("شماره تراکنش:", "Transaction ID:"),
    ("مبلغ پرداختی:", "Amount paid:"),
    ("تاریخ:", "Date:"),
    ("تومان", "Toman"),
    ("ریال", "Rial"),
    ("گیگابایت", "GB"),
    ("مگابایت", "MB"),
    ("نام کاربری سرویس", "Service username"),
    ("نام کاربری کانفیگ", "Config username"),
    ("نام کاربری", "Username"),
    ("نام محصول", "Product"),
    ("موجودی کیف پول", "Wallet balance"),
    ("موجودی", "Balance"),
    ("حجم باقی مانده", "Data remaining"),
    ("حجم مصرفی", "Data used"),
    ("حجم سرویس", "Service data"),
    ("حجم اضافه", "Extra data"),
    ("زمان اضافه", "Extra time"),
    ("مدت زمان", "Duration"),
    ("تاریخ اتمام", "Expires"),
    ("فعال تا تاریخ", "Active until"),
    ("اخرین زمان اتصال", "Last connection"),
    ("آخرین زمان اتصال", "Last connection"),
    ("وضعیت سرویس", "Service status"),
    ("موقعیت سرویس", "Service location"),
    ("لینک اشتراک", "Subscription link"),
    ("لینک اتصال", "Connection link"),
    ("پیش فاکتور شما", "Your invoice"),
    ("فاکتور تمدید شما", "Your renewal invoice"),
    ("فاکتور خرید", "Purchase invoice"),
    ("سفارش شما آماده پرداخت است", "Your order is ready to pay"),
    ("برای تایید و پرداخت روی دکمه زیر کلیک کنید", "Tap the button below to confirm and pay"),
    ("برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید", "Tap the button below to confirm and renew"),
    ("جهت پرداخت از دکمه زیر استفاده کنید", "Use the button below to pay"),
    ("جهت پرداخت از دکمه زیر استفاده", "Use the button below to pay"),
    ("تراکنش شما ایجاد شد", "Your transaction was created"),
    ("فاکتور پرداخت ایجاد شد", "Payment invoice created"),
    ("کد پیگیری", "Tracking code"),
    ("شماره فاکتور", "Invoice number"),
    ("مبلغ فاکتور", "Invoice amount"),
    ("مبلغ تراکنش", "Transaction amount"),
    ("مبلغ تمدید", "Renewal price"),
    ("مبلغ قابل پرداخت", "Amount due"),
    ("تعرفه هر", "Price per"),
    ("حداقل حجم", "Minimum data"),
    ("حداکثر حجم", "Maximum data"),
    ("هر ماه معادل ۳۰ روز است", "Each month equals 30 days"),
    ("فقط گزینه‌های زیر قابل انتخاب هستند", "Only the options below can be selected"),
    ("لطفا به این نکات قبل از پرداخت توجه کنید", "Please read these notes before paying"),
    ("این تراکنش به مدت یک روز اعتبار دارد", "This transaction is valid for one day"),
    ("این تراکنش به مدت ۲۴ ساعت اعتبار دارد", "This transaction is valid for 24 hours"),
    ("این تراکنش به مدت یک ساعت اعتبار دارد", "This transaction is valid for one hour"),
    ("پس از آن امکان پرداخت این تراکنش امکان ندارد", "After that it cannot be paid"),
    ("در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید", "If you have a problem, contact support"),
    ("در صورت مشکل، با پشتیبانی در ارتباط باشید", "If you have a problem, contact support"),
    ("سرویس با موفقیت ایجاد شد", "Service created successfully"),
    ("سرویس با موفقیت حذف شد", "Service deleted"),
    ("تمدید برای سرویس شما با موفقیت صورت گرفت", "Your service was renewed"),
    ("افزایش حجم برای سرویس شما با موفقیت صورت گرفت", "Extra data was added to your service"),
    ("افزایش زمان برای سرویس شما با موفقیت صورت گرفت", "Extra time was added to your service"),
    ("کانفیگ شما باموفقیت به سرور", "Your config was moved to server"),
    ("انتقال یافت", "successfully"),
    ("زیر مجموعه گیری", "Referrals"),
    ("زیرمجموعه‌گیری", "Referrals"),
    ("دریافت هدیه عضویت", "Claim signup bonus"),
    ("زیرمجموعه", "referral"),
    ("نمایندگی پیشرفته", "Advanced agent"),
    ("ارسال نشده است", "Not submitted"),
    ("تایید شده توسط ادمین", "Verified by admin"),
    ("متصل نشده", "Not connected"),
    ("نامشخص", "Unknown"),
    ("نامحدود", "Unlimited"),
    ("گیگ", "GB"),
    (" روز", " days"),
    (" ساعت", " hours"),
    (" دقیقه", " minutes"),
    (" ماه ", " month "),
]


def looks_admin(s: str) -> bool:
    if s.strip().upper().startswith("SELECT ") or " FROM " in s.upper():
        return True
    return any(m in s for m in ADMIN_MARKERS)


def should_skip(inner: str) -> bool:
    if inner in SKIP_EXACT:
        return True
    if looks_admin(inner):
        return True
    return False


QUOTE_RE = re.compile(r'("(?:\\.|[^"\\])*"|\'(?:\\.|[^\'\\])*\')', re.S)
PERSIAN_RE = re.compile(r"[\u0600-\u06FF]")


def unescape_php_str(s: str, quote: str) -> str:
    # Keep PHP escapes as-is for matching; we operate on raw inner content
    return s


def apply_phrases(s: str) -> str:
    out = s
    for src, dst in sorted(PHRASES, key=lambda x: len(x[0]), reverse=True):
        if src in out:
            out = out.replace(src, dst)
    return out


def translate_inner(inner: str) -> str:
    if not PERSIAN_RE.search(inner):
        return inner
    if should_skip(inner):
        return inner
    if inner in EXACT:
        return EXACT[inner]
    # try after normalizing newlines already exact
    phrased = apply_phrases(inner)
    return phrased


def process_file(path: Path, phrases: bool = True) -> int:
    text = path.read_text(encoding="utf-8")
    n = 0

    def repl(m: re.Match) -> str:
        nonlocal n
        token = m.group(0)
        q = token[0]
        inner = token[1:-1]
        if not PERSIAN_RE.search(inner) or should_skip(inner):
            return token
        if inner in EXACT:
            new_inner = EXACT[inner]
        elif phrases:
            new_inner = apply_phrases(inner)
        else:
            new_inner = inner
        if new_inner != inner:
            n += 1
            return q + new_inner + q
        return token

    new = QUOTE_RE.sub(repl, text)
    if new != text:
        path.write_text(new, encoding="utf-8")
    return n


def main():
    jobs = [
        ("index.php", True),
        ("function.php", False),
        ("keyboard.php", False),
        ("withdraw_lib.php", False),
        ("webhooks.php", True),
        ("development_mode.php", True),
        ("api/miniapp.php", True),
        ("api/users.php", True),
        ("api/invoice.php", True),
    ]
    total = 0
    for rel, phrases in jobs:
        p = ROOT / rel
        c = process_file(p, phrases=phrases)
        print(f"{rel}: {c} replacements")
        total += c
    print("total", total)


if __name__ == "__main__":
    main()
