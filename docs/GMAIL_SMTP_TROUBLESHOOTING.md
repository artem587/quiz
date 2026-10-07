# Gmail SMTP troubleshooting

Use:
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your@gmail.com
SMTP_PASS=your Google App Password (16 characters; spaces are removed automatically)
SMTP_FROM=the same Gmail address
SMTP_FROM_NAME=QuizSpace

The app now logs the exact SMTP stage to:
storage/mail.log
The directory is blocked by .htaccess.

Important:
- Use a Google App Password, not your normal Gmail password.
- The sender must be the same account used for SMTP_USER.
- The recipient can be any valid user email.
- After changing .env, retry password reset or new-device verification.
