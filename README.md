# Advertisement Platform

A comprehensive Laravel-based platform for managing advertisements, user accounts, banners, and administration.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Project Structure](#project-structure)
  - [Public Routes](#public-routes)
  - [Authentication](#authentication)
  - [Cabinet (User Dashboard)](#cabinet-user-dashboard)
  - [Admin Panel](#admin-panel)
- [Middleware](#middleware)
- [Dependencies](#dependencies)

## Features

- User authentication with email verification
- Phone number verification and SMS authentication
- Social network login integration
- Advertisement management system
- Banner advertisements with tracking
- User favorites and messaging system
- Support tickets
- Administration panel with user, regions, and category management
- Moderation system for advertisements and banners

## Installation

```
# Clone the repository
git clone [repository_url]

# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations and seeders
php artisan migrate
php artisan db:seed

# Compile assets
npm run dev
```

## Project Structure

The application is structured around several main components:

### Public Routes

Public routes are accessible to all users, including guests:

- Home page (`/`)
- Authentication routes (including email verification)
- Phone authentication (`/login/phone`)
- Social network authentication (`/login/{network}`)
- Banner display and tracking
- Advertisement browsing and search

```
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/adverts/search', [AdvertController::class, 'search'])->name('adverts.search');
Route::get('/adverts/show/{advert}', [AdvertController::class, 'show'])->name('adverts.show');
```

### Authentication

The application supports multiple authentication methods:

- Email and password (with verification)
- Phone number with SMS token
- Social networks integration

Email verification is enabled through Laravel's built-in verification system:

```
Auth::routes(['verify' => true]);
```

### Cabinet (User Dashboard)

The cabinet area provides authenticated users with access to their personal dashboard:

- Profile management
- Phone verification
- Advertisement management
  - Creating advertisements
  - Editing advertisements
  - Sending advertisements for moderation
  - Closing advertisements
- Favorites management
- Banner management
- Ticket system for support
- Messaging system between users

All cabinet routes require authentication and email verification:

```
Route::group(
    [
        'prefix' => 'cabinet',
        'as' => 'cabinet.',
        'middleware' => ['auth', 'verified'],
    ],
    function (): void {
        // Cabinet routes here
    }
);
```

### Admin Panel

The admin panel provides administrative functions:

- Dashboard with analytics
- User management
- Region management
- Category and attribute management
- Advertisement moderation
- Banner moderation
- Ticket management
- Page content management

Admin routes are protected by authentication and authorization:

```
Route::group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => ['auth', 'can:admin-panel'],
], function (): void {
    // Admin routes here
});
```

## Middleware

The application uses several custom middleware:

- `verified`: Ensures the user's email is verified
- `FilledProfile`: Ensures the user has completed their profile before accessing certain features
- `can:admin-panel`: Authorization check for admin panel access

## Dependencies

This application integrates with:

- **Elasticsearch**: For advanced search functionality across advertisements
- **SMS Service**: For phone verification and authentication
- **Social Authentication**: For third-party login options

## Development

### Running Tests

```
php artisan test
```

### Generating Documentation

```
php artisan scribe:generate
```

## License

[MIT](LICENSE)