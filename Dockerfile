# Use the official PHP image from Docker Hub
FROM php:7.4-apache

# Copy the project files to the Apache web server's document root
COPY . /var/www/html/

# Expose port 80 for web access
EXPOSE 80
