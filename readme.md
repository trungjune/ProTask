# ProTask

A Task management system with time tracking feature!


## Installation

### cPanel installation

- Copy all files under ProTask folder to your server's root or public_html directory
- Just visit your website - and follow the installation process

### NGinx installation

- Copy ProTask to your server
- Set document root as per your directory location where you did put that ProTask files.


### Full Documentation:
You will find a full documentation instructions on the following link.
https://w3bd.gitbook.io/protask/

#### Clear All Cache
```
php artisan optimize && php artisan cache:clear && php artisan route:cache && php artisan view:clear && php artisan config:cache && php artisan route:clear 
```


## Running a queue Cron job on shared hosting

```
php /path/to/application/artisan queue:work --queue=high,default --stop-when-empty
```
