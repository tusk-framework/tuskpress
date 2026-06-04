# TuskPress

> 💡 **Example Project**  
> TuskPress is a canonical example application created to demonstrate how to build a real-world, high-performance application using the **Tusk Framework** running natively on the **Tusk Engine**. It serves as a reference architecture and a learning resource for developers.

TuskPress is a fully functional, ultra-fast content management system (inspired by WordPress). It showcases how to leverage clean architecture, Dependency Injection, Repositories, and the Long-Lived Process model within the Tusk ecosystem.

## ✨ Key Features

- **Long-Lived Process Architecture**: Powered by Go (Tusk Engine), PHP stays loaded in memory, eliminating the "boot-and-die" overhead of traditional PHP apps.
- **Modern Admin UI**: A beautiful, responsive, glassmorphism-inspired dark mode admin panel.
- **Familiar Ecosystem**: Supports WordPress-style hooks (Action/Filter) via our custom `HookBus`, themes, and plugins without legacy spaghetti code.
- **Domain-Driven Design (DDD)**: Clean domain models (Post, User, Meta, Term) decoupled from the infrastructure layer using Doctrine-inspired Repositories.
- **Native Async Jobs**: Built-in background job execution for tasks like publishing scheduled posts, without relying on web-traffic-triggered crons.

## 🚀 How to Run (Docker Compose)

TuskPress is designed to run isolated, without the need to install PHP, Nginx, or Apache on your host machine. The project includes an Alpine-based image running the Tusk Engine in Go.

To bring up the project (ensure Docker Desktop is running):

```bash
# 1. Build the image (it will automatically install dependencies via Composer)
docker-compose build

# 2. Start the service in the background (MySQL database and the Tusk Web Server)
docker-compose up -d
```

Access the application in your browser: **[http://localhost:8080](http://localhost:8080)**  
Access the Admin Panel: **[http://localhost:8080/wp-admin](http://localhost:8080/wp-admin)**

*(The default credentials are auto-generated on first install or via the initial migration script.)*

## 🧠 Architecture: The End of "Boot-and-Die"

Unlike traditional PHP (where Nginx triggers FPM to load the entire application on every request), TuskPress uses the **Long-Lived Process** concept.

1. When you run the container, the `tusk-engine` (in Go) opens port 8080 and starts the `worker.php` file in the background.
2. The `worker.php` loads the IoC Container and all repositories into memory **only once**.
3. New incoming requests to the HTTP server (Go) are rapidly serialized via *NDJSON* and injected into the framework's standard input (STDIN).
4. PHP processes the business logic and returns the response (STDOUT). Memory remains intact, making responses incredibly fast.

### 🗂️ Project Structure

- `worker.php` — The *Entry Point* of the Tusk Engine that keeps the infinite request loop active.
- `public/` — Static files (CSS, JS, Images) and the frontend bootstrap (`index.php`).
- `public/wp-content/` — Home for Themes (`tusk-modern`) and Plugins.
- `bootstrap/app.php` — Central initialization of the Container (`Tusk\Core\Container\Container`) and the HTTP Kernel.
- `src/` — Core source code (Controllers, Middleware, Domain Entities, Infrastructure).
- `migrations/` — SQL scripts for database initialization and schema setup.

## 🎨 Themes & Plugins

TuskPress keeps the familiar extensibility of WordPress but implements it with modern PHP 8.2 features.

- **Themes**: Reside in `public/wp-content/themes/`. The default `tusk-modern` theme uses standard templates (`index.php`, `single.php`, `page.php`).
- **Plugins**: Hook into the system lifecycle via the `HookBus`. Create a single `.php` file or a directory in `public/wp-content/plugins/` to get started.

## 🤝 The Tusk Ecosystem

This project is part of the official Tusk ecosystem:

- [Tusk Engine](https://github.com/tusk-framework/tusk-engine) - The Go heart.
- [Tusk Framework](https://github.com/tusk-framework/tusk-framework) - The PHP brain.
- [Tusk Docs](https://github.com/tusk-framework/tusk-docs) - The full documentation.

## 📄 License

This project is open-source and available under the MIT License.
