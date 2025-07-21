# Bulk SMS Messaging System 

A complete PHP messaging system with Slim Framework, MySQL database, and **TalkSasa SMS API** integration featuring user authentication, bulk SMS sending, and a responsive web dashboard.

## Features

- **User Authentication**: JWT token-based authentication with 24-hour expiration
- **Single & Bulk SMS**: Send messages to individual recipients or up to 100 recipients per batch
- **Contact Management**: Add, upload via CSV, and manage contacts  
- **Delivery Tracking**: Real-time status tracking and message history
- **Responsive Dashboard**: Clean, mobile-friendly interface built with Tailwind CSS
- **Rate Limiting**: Built-in delays for bulk messaging to respect API limits
- **Multiple SMS Providers**: Currently integrated with TalkSasa SMS API

## Tech Stack

- **Backend**: PHP 8.4+ with Slim Framework 4
- **Database**: MySQL 5.7+
- **Frontend**: HTML/CSS/JavaScript with Tailwind CSS
- **SMS Provider**: TalkSasa SMS API
- **Authentication**: JWT tokens with Firebase JWT library

## Prerequisites

- PHP 8.4 or higher
- MySQL 5.7 or higher (via XAMPP recommended)
- Composer
- TalkSasa SMS API account with API credentials

## Quick Start

### 1. Clone and Install Dependencies
```bash
git clone <repository-url>
cd messaging-system
composer install
```

### 2. Database Setup
Start MySQL (via XAMPP or standalone):
```bash
# Import database schema
mysql -u root -p < config/database.sql
```

### 3. Environment Configuration
Copy `.env.example` to `.env` and configure:
```env
# Database
DB_HOST=localhost
DB_NAME=messaging_system
DB_USER=root
DB_PASS=

# Security
JWT_SECRET=your_secure_jwt_secret_key

# TalkSasa SMS API
TALKSASA_API_KEY=your_talksasa_api_key
TALKSASA_API_URL=https://bulksms.talksasa.com/api/v3/sms/send
TALKSASA_SENDER_ID=TALKSASA
```

### 4. Start Development Server
```bash
composer start
# or
php -S localhost:8000 -t public
```

### 5. Access Application
Open `http://localhost:8000` in your browser

## API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `GET /api/auth/profile` - Get user profile (requires auth)

### Messages
- `POST /api/messages/send` - Send single SMS
- `POST /api/messages/send-bulk` - Send bulk SMS (max 100 recipients)
- `GET /api/messages/history` - Get message history
- `GET /api/messages/{id}/status` - Get message status

### Contacts
- `GET /api/contacts` - List user contacts
- `POST /api/contacts` - Add new contact
- `POST /api/contacts/upload` - Upload contacts via CSV
- `DELETE /api/contacts/{id}` - Delete contact

##  SMS Pricing

**TalkSasa SMS Costs:**
- **Kenya**: KES 1.0 per SMS
- **International**: Varies by destination
- **Bulk discounts**: Available for high-volume accounts

##  Current Status

###  Working Features
- **User Authentication**: Complete JWT-based auth system
- **Single SMS**: Individual message sending with delivery confirmation
- **Bulk SMS**: Up to 100 recipients with individual tracking
- **Contact Management**: Add, upload CSV, manage contacts
- **Message History**: View all sent messages with status
- **TalkSasa Integration**: Full API integration with retry mechanism
- **Error Handling**: Comprehensive error logging and user feedback

###  Recent Updates
- **Migration from Twilio to TalkSasa** (July 2025)
- **Authentication Bearer tokens** implementation
- **Retry mechanism** for failed API calls
- **Improved error handling** with detailed logging
- **Phone number validation** with international format support

## Development

### Project Structure
```
messaging-system/
├── config/
│   ├── Database.php
│   └── database.sql
├── public/
│   ├── index.php
│   ├── css/
│   └── js/app.js
├── src/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Models/
│   └── Services/
├── templates/
│   └── dashboard.html
├── .env
├── composer.json
└── README.md
```

### Adding New Features
1. Create controllers in `src/Controllers/`
2. Add models in `src/Models/`
3. Define routes in `public/index.php`
4. Update frontend in `templates/dashboard.html`

##  Troubleshooting

### Common Issues

**Database Connection:**
- Ensure MySQL is running (XAMPP)
- Check `.env` credentials
- Verify `messaging_system` database exists

**SMS Sending:**
- Verify TalkSasa API key in `.env`
- Check phone number format: `+254XXXXXXXXX`
- Ensure sender ID is authorized in TalkSasa dashboard
- Check account balance

**Authentication:**
- Clear browser localStorage if login issues occur
- Verify JWT_SECRET is set in `.env`
- Check token expiration (24 hours)

##  SMS Provider Details

**TalkSasa SMS API:**
- **Endpoint**: `https://bulksms.talksasa.com/api/v3/sms/send`
- **Authentication**: Bearer Token
- **Formats**: Plain text, Unicode support
- **Delivery Reports**: Real-time status updates
- **Rate Limits**: Built-in 100ms delays between bulk sends

##  Security Features

- JWT token authentication with expiration
- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- Input validation and sanitization
- API rate limiting and error handling

##  Performance

- **Database**: Optimized with indexes on frequently queried columns
- **API Calls**: Retry mechanism with exponential backoff
- **Bulk Operations**: Efficient individual sends with rate limiting
- **Error Handling**: Graceful degradation with detailed logging

##  Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/new-feature`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/new-feature`)
5. Create Pull Request


##  Support

For issues and questions:
1. Check the troubleshooting section above
2. Review error logs in browser console
3. Verify all environment variables are set correctly
4. Contact TalkSasa support for SMS API issues

---

**Built with ❤️ using PHP, Slim Framework, and TalkSasa SMS API**
