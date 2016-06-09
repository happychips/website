# Happy Chips website

This is a content management system for the Happy Chips website.

## Installation

Download the application

    git clone https://github.com/happychips/website.git happychips-website
    cd happychisps-website

Install dependencies with [composer](http://getcomposer.org)

    composer install

Rename `user-example` to `user`

    mv user-example user

Modify global page settings in `user/site.json`

Launch the application using the built-in webserver

    php -S localhost:8000 index.php

The page is now available under `http://localhost:8000`
and the admin area under `http://localhost:8000/admin`
with username `admin` and password `admin` (can be set in `user/data/site.json`).

## Deployment

For accessing the application with apache, create an `.htaccess` file with the following content

    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ /index.php/$1 [L,QSA]