# Wicket - Memberships plugin for WordPress

This plugin is a part of the Wicket ecosystem and is used to manage memberships in WordPress.

## Development Environment Setup Notes

* This project uses composer for dependency management. Run `composer install` to install dependencies.
* Project uses PSR-4 class autoload. You will need to run `composer dump-autoload` to re-generate the autoload files every time you add a new class. To generate production autoload files, run `composer dump-autoload -o`.
* This project uses react-based components for the admin interface. To build the assets, go to the /frontend directory (`cd frontend`) and run `npm install` to install the dependencies and then run `npm run start` to build the development version of the assets. To build the production version of the assets, run `npm run build`.

This readme will be updated as the project evolves.

## Testing
* Install development packages if you haven't with: `docker compose exec php bash -c "cd /var/www/html/web/app/plugins/wicket-wp-memberships && composer install"`
* Install WordPress testing instance with: `docker compose exec php bash -c "cd /var/www/html/web/app/plugins/wicket-wp-memberships && bin/install-wp-tests.sh wordpress_test root 'root' mariadb latest"`
* Now you can run the tests: `docker compose exec php bash -c "cd /var/www/html/web/app/plugins/wicket-wp-memberships && ./vendor/bin/phpunit"`
* You will also need to remove dev packages from autoload once you plan to push changes to git: `docker compose exec php bash -c "cd /var/www/html/web/app/plugins/wicket-wp-memberships && composer install --no-dev"`
* Don't forget to cover complex logic parts of your code with tests. Test it, or trust it to fail.