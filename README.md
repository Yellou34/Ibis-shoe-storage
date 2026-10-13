# Shoe Stock

A small website for counting shoes in storage. Each shoe has a photo, a brand, a colour, an optional serial code, and a count for every size. Only people with an account can open it.

**What it does**

- Same brand, different colour: each colour gets its own card with its own photo.
- Same brand and colour, different size: the sizes sit together on one card.
- Different brands in the same size: the **By size** view groups them.
- Plus and minus buttons for each size, Low and Out markers, search by brand, colour, serial code or size.
- A History page showing who added or removed what, and when.
- Sign-in with three roles: **admin**, **staff** and **viewer**.

**You need:** PHP 8.0 or newer (with the PDO MySQL and GD extensions, both normal on XAMPP and most hosts) and MySQL or MariaDB.

---

## A. Run it on your own computer (XAMPP)

1. Install XAMPP and start **Apache** and **MySQL** in the XAMPP control panel.
2. Copy the whole `shoe-stock` folder into `C:\xampp\htdocs\` (Mac: `/Applications/XAMPP/htdocs/`).
3. Open `http://localhost/phpmyadmin`, click the **Import** tab, choose `sql/database.sql` and click **Import**. This creates the `shoe_store` database and its tables.
4. Open `includes/config.php`. The XAMPP defaults (user `root`, empty password) already work, so you can leave it.
5. Go to `http://localhost/shoe-stock/`. The first time, it asks you to create the administrator account.
6. Sign in, open **People** to add the others, then **Add shoes**.

## B. Put it online

1. Get hosting with PHP and MySQL (cPanel shared hosting or a VPS both work). Make sure the site uses **HTTPS**, because people type passwords into it. Most hosts give a free certificate.
2. In your host's panel, create a MySQL database and a database user, and give the user full rights on that database. Write down the database name, user and password.
3. Open phpMyAdmin, select that database, open `sql/database.sql` in a text editor, **delete the first two statements** (`CREATE DATABASE ...` and `USE ...`), then import the file.
4. Edit `includes/config.php` and fill in `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASS` from step 2. `DB_HOST` is usually `localhost`.
5. Upload everything in this folder to the site's `public_html` (or a sub-folder such as `public_html/stock`). Make sure the `uploads` folder is writable (permission 755, or 775 if photos fail to save).
6. Visit the site, create the administrator account, then **delete `install.php`** from the server. It stops working once an account exists, but there is no reason to leave it.

## C. Keeping it private

Everything is behind a sign-in. Nobody can see stock or photos without an account that an administrator created.

- **Add people only from the People page.** Choose **viewer** for people who only need to look, **staff** for people who add and change stock, **admin** for yourself. Switching someone off locks them out immediately.
- **Photos are private too.** They are stored in `uploads/`, which the web server refuses to serve directly. `photo.php` only sends them to signed-in people.
- **Use HTTPS.** Without it, passwords and photos travel unprotected.
- **Optional extra lock.** Most hosts have a "Password protect directories" tool. Turning it on for this folder asks for a second password before the sign-in page even appears.
- **Share the address only with the people you added.** The pages also tell search engines not to list them.

If your server is **Nginx** instead of Apache, the `.htaccess` files are ignored. Add this inside the `server` block so the private folders stay blocked:

```nginx
location ~ ^/(includes|sql|uploads)/ { deny all; return 404; }
```

## Roles

| Role | Look at stock and history | Add shoes, change counts, edit | Remove a shoe, add people |
|------|:---:|:---:|:---:|
| viewer | yes | no | no |
| staff | yes | yes | no |
| admin | yes | yes | yes |

## Using it

- **Add shoes:** brand, colour, optional serial code, size, pairs received, and a photo (on a phone the photo box opens the camera). If that brand and colour already exists, or the serial code matches, the pairs are added to the existing card instead of making a second one.
- **Another size of a shoe you already have:** use **+ Add size** on its card.
- **Another colour of the same brand:** use **Add shoes** and type the same brand with the new colour.
- **Sales and returns:** use the − and + buttons under each size.
- **Stock take:** open **Edit** on a card and type the real counts. Each correction is saved in History.

## Files

```
index.php      stock list (By shoe / By size, search, + and -)
add.php        receive shoes, add a size
edit.php       edit details, correct counts, remove a shoe (admin)
users.php      add people, roles, switch off, reset passwords (admin)
history.php    log of every change
account.php    change your own password
login.php, logout.php, install.php, adjust.php, photo.php
includes/      settings and shared code (blocked from the web)
sql/           database.sql (blocked from the web)
uploads/       private photos (blocked from the web)
assets/        style.css, app.js
```

## Backups

Export the database from phpMyAdmin (**Export**, Quick, SQL) and copy the `uploads` folder. Do both, because the database holds the counts and `uploads` holds the photos.

## Problems

- **"Cannot connect to the database":** check the four `DB_` values in `includes/config.php`.
- **"Database not set up":** import `sql/database.sql` (step A3 or B3).
- **A photo will not save:** the `uploads` folder is not writable, or the photo is over 8 MB. Photos are shrunk when saved.
- **A blank page or an error 500:** set `SHOW_ERRORS` to `true` in `includes/config.php`, reload to see the message, then set it back to `false`.
- **Forgot the administrator password:** in phpMyAdmin, delete the row in the `users` table and open `install.php` again to create a new administrator (only works when the table is empty), or ask another admin to set a new password on the People page.

## What protects it

Passwords are stored hashed, never as text. Every form carries a one-time token, every database query uses prepared statements, and every value shown on a page is escaped. Sign-in slows down after 8 wrong attempts from one address in 10 minutes. Uploaded photos are re-saved as plain JPEGs under random names, so a disguised file cannot run on the server.
