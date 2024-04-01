# Wicket - Memberships plugin for WordPress

This plugin is a part of the Wicket ecosystem and is used to manage memberships in WordPress.

## Development Environment Setup Notes

* This project uses composer for dependency management. Run `composer install` to install dependencies.
* Project uses PSR-4 class autoload. You will need to run `composer dump-autoload` to re-generate the autoload files every time you add a new class. To generate production autoload files, run `composer dump-autoload -o`.
* This project uses react-based components for the admin interface. To build the assets, go to the /frontend directory (`cd frontend`) and run `npm install` to install the dependencies and then run `npm run start` to build the development version of the assets. To build the production version of the assets, run `npm run build`.

This readme will be updated as the project evolves.