<?php
// ------------------------------------------------------------
// Settings. Edit the values below for your server.
// ------------------------------------------------------------

// Database (the values XAMPP uses by default are shown).
const DB_HOST = 'localhost';
const DB_NAME = 'shoe_store';
const DB_USER = 'root';
const DB_PASS = '';

// Leave empty for MySQL/MariaDB. Only change if you know you need a custom PDO DSN.
const DB_DSN = '';

const APP_NAME     = 'Shoe Stock';
const TIMEZONE     = 'Africa/Nairobi';
const LOW_STOCK    = 3;        // a size with this many pairs or fewer is marked Low
const MAX_UPLOAD_MB = 8;       // largest photo accepted (photos are shrunk when saved)
const SESSION_NAME = 'shoestock';

// Set to true only while fixing setup problems. Turn it off again afterwards.
const SHOW_ERRORS  = false;
