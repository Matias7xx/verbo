---
description: Repository Information Overview
alwaysApply: true
---

# SIVOP - PCPB Repository Information

## Summary
SIVOP (Sistema de Vídeo Oitivas da Polícia) is a specialized Laravel application developed for the Polícia Civil da Paraíba (PCPB). It facilitates the recording, management, and viewing of video hearings ("oitivas"). The system leverages **Inertia.js** with **Vue 3** and **PrimeVue** for a seamless SPA experience, and includes features like chunked video uploads (via **RecordRTC**), signed route validation for secure access, and **FFmpeg** integration for video processing.

## Structure
- **app/**: Core Laravel logic (Models, Controllers, Services, Jobs, and Helpers).
- **bootstrap/**: Framework bootstrap and cache files.
- **config/**: Application configuration files (database, mail, services, etc.).
- **database/**: Database migrations, factories, and seeders (PostgreSQL).
- **docker/**: Custom Docker configuration files for Apache, PHP, and Supervisor.
- **public/**: Public entry point and assets.
- **resources/**: Frontend source files (Vue components in `js/Pages`, CSS, and Blade views).
- **routes/**: Route definitions (`web.php` for Inertia routes, `api.php`, `auth.php`).
- **storage/**: Application storage for logs, framework files, and temporary uploads.
- **tests/**: PHPUnit test suite (Feature and Unit tests).

## Language & Runtime
**Language**: PHP, JavaScript  
**Version**: PHP ^8.2 (8.4 in Docker), Node.js (Latest)  
**Build System**: Vite (Frontend), Composer (Backend)  
**Package Manager**: npm, composer

## Dependencies
**Main Dependencies**:
- **Laravel 12**: Core PHP framework.
- **Inertia.js**: Bridges Laravel and Vue 3.
- **Vue 3**: Frontend reactive framework.
- **PrimeVue**: UI component library.
- **RecordRTC**: Client-side video recording.
- **FFmpeg**: Server-side video processing (in Docker).
- **PostgreSQL**: Primary database.
- **MinIO/S3**: Object storage for video files.

**Development Dependencies**:
- **Laravel Breeze**: Authentication scaffolding.
- **Vite**: Frontend build tool.
- **Tailwind CSS**: Utility-first CSS framework.
- **PHPUnit**: Testing framework.

## Build & Installation
```bash
# Automated setup (includes composer install, .env creation, key generation, and migrations)
composer setup

# Manual installation
composer install
npm install
npm run build
php artisan migrate
```

## Docker

**Dockerfile**: `./Dockerfile` (Based on `php:8.4-apache`)
**Image**: `verbo-app`
**Configuration**: 
- Multi-service setup using `docker-compose.yml`.
- **app**: PHP 8.4 + Apache + Node.js + FFmpeg + Supervisor.
- **db**: PostgreSQL 16.
- **minio**: S3-compatible storage for video data.
- Includes a custom entrypoint script at `docker/entrypoint.sh`.

## Testing

**Framework**: PHPUnit
**Test Location**: `tests/`
**Naming Convention**: `*Test.php`
**Configuration**: `phpunit.xml`

**Run Command**:
```bash
php artisan test
# or via composer script
composer test
```

## Main Files & Resources
- **Backend Entry Point**: `public/index.php`
- **Frontend Entry Point**: `resources/js/app.js`
- **Primary Routes**: `routes/web.php` (contains `sala-gravacao` and `assistir` signed routes).
- **Vue Pages**: `resources/js/Pages/` (e.g., `Oitivas/PublicPlayer.vue`).
- **Supervisor Config**: `docker/supervisor/supervisord.conf` (manages queue workers).
