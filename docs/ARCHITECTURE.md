# System Architecture

## Overview

The system follows a Monolithic architecture using **Laravel** as the API & Backend framework, tightly integrated with **Vue 3** via **Inertia.js** for a modern Single Page Application (SPA) feel without the complexity of a separate API repository.

## Layers

### 1. Presentation Layer (Frontend)

- **Framework**: Vue.js 3
- **Glue**: Inertia.js (Server-driven routing)
- **Styling**: Tailwind CSS (Utility-first)
- **Design System**: Glassmorphism, Premium UI Components.
- **State Management**: Pinia (if needed) or simple Props/Event bus.

### 2. Application Layer (Backend)

- **Framework**: Laravel 11/12
- **Auth**: Laravel Breeze/Jetstream (or custom Fortify implementation)
- **Real-time**: Laravel Reverb/Pusher (for Queue & Pharmacy status)
- **PDF**: DomPDF (for printing receipts & prescriptions)

### 3. Data Layer

- **Database**: MySQL 8.0
- **ORM**: Eloquent

## Directory Structure (Planned)

```
/app
  /Http
    /Controllers
      /Admin
      /Doctor
      /Patient
      AuthController.php
  /Models (User, Patient, Doctor, etc.)
/resources
  /js
    /Pages (Inertia Views)
      /Auth
      /Admin
      /Doctor
      /Public
    /Components (Reusable UI)
    /Layouts
```

## Deployment

- **Server**: Ubuntu 20.04/22.04 LTS
- **Web Server**: Nginx
- **Process Manager**: Supervisor (for Queues & Reverb)
