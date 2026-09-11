# Dawei Website

## 1. Requirements

Sebelum melakukan instalasi dan menjalankan aplikasi Dawei Website, pastikan komputer sudah memiliki beberapa software berikut:

### 1.1 Operating System

* Windows 10 / Windows 11
* Sistem operasi **64-bit**

### 1.2 Web Server

Gunakan **Laragon** sebagai local development environment.

Pastikan Laragon sudah ter-install dan dapat menjalankan:

* Apache
* MySQL

### 1.3 PHP

Project menggunakan:

* **PHP 8.3.30**
* PHP **64-bit**

> Pastikan PHP yang digunakan adalah versi 64-bit. PHP 32-bit tidak dapat digunakan untuk project ini.

Untuk mengecek versi PHP:

```bash
php -v
```

### 1.4 Composer

Composer diperlukan untuk meng-install dependency Laravel.

Cek instalasi Composer:

```bash
composer -V
```

### 1.5 Database

Project menggunakan **MySQL** sebagai database.

Database dapat dikelola melalui:

* Laragon
* phpMyAdmin
* MySQL

### 1.6 Project Information

Project:

```text
Dawei-Website
```

Framework:

```text
Laravel 10.48.29
```

PHP:

```text
8.3.30
```

Database:

```text
MySQL
```

Local development environment:

```text
Laragon
```

## 2. Installation / Setup Project

Ikuti langkah berikut untuk melakukan instalasi project Dawei Website pada komputer baru.

### 2.1 Clone Repository

Clone project dari repository Git menggunakan perintah:

```bash
git clone https://github.com/Aktivo-Dawei/Dawei-Website.git
```

### 2.2 Masuk ke Folder Project

Masuk ke folder project menggunakan command:

```bash
cd Dawei-Website
```

Pastikan posisi terminal sudah berada di dalam folder project sebelum melanjutkan ke tahap berikutnya.

### 2.3 Install Dependency

Install seluruh dependency Laravel menggunakan Composer:

```bash
composer install
```

Tunggu sampai proses instalasi selesai.

### 2.4 Membuat File `.env`

Copy file `.env.example` menjadi file `.env`.

Pada Windows, dapat menggunakan:

```bash
copy .env.example .env
```

Setelah command dijalankan, pastikan terdapat file:

```text
.env
```

di dalam folder project.

### 2.5 Konfigurasi File `.env`

Buka file `.env` menggunakan Visual Studio Code atau text editor lainnya.

Sesuaikan konfigurasi database dengan database yang akan digunakan.

Contoh:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dawei
DB_USERNAME=root
DB_PASSWORD=
```

> Sesuaikan `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dengan konfigurasi MySQL pada komputer yang digunakan.

### 2.6 Generate Application Key

Generate application key Laravel menggunakan:

```bash
php artisan key:generate
```

Pastikan command berhasil dan nilai `APP_KEY` otomatis terisi pada file `.env`.

### 2.7 Migration Database

Setelah konfigurasi database selesai, jalankan migration:

```bash
php artisan migrate
```

Laravel akan membuat tabel-tabel yang dibutuhkan oleh aplikasi ke dalam database.

### 2.8 Menjalankan Application

Setelah proses migration selesai, jalankan Laravel development server:

```bash
php artisan serve
```

Jika berhasil, Laravel akan menampilkan alamat seperti:

```text
http://127.0.0.1:8000
```

Buka alamat tersebut melalui browser untuk mengakses Dawei Website.
