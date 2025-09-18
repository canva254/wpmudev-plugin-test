# Dependency Management & Composer Isolation

This plugin uses Composer for dependency management. To avoid conflicts with other plugins or themes, follow these best practices:

## 1. Namespace Isolation (PHP-Scoper)
- Use [php-scoper](https://github.com/humbug/php-scoper) to prefix all Composer dependencies with a unique namespace (e.g., `WPMUDEV\PluginTest\Vendor`).
- This prevents global class/function conflicts in the WordPress environment.
- Run php-scoper after `composer install` and before packaging the plugin for production.

## 2. Autoloading
- Composer's autoloader is configured to use classmap for `core/` and `app/` directories.
- All plugin classes should use the `WPMUDEV\PluginTest` namespace.

## 3. Production Packaging
- Do **not** include development dependencies (see `.npmignore` for npm, and exclude `require-dev` for Composer) in production builds.
- Only ship the `vendor/` directory after running php-scoper.

## 4. Updating Dependencies
- Update dependencies using Composer as usual.
- After updating, always re-run php-scoper to re-prefix the vendor code.

## 5. Documentation
- Document any manual steps for dependency isolation in your README or build scripts.

---

### Example Composer Isolation Command

```
php-scoper add-prefix --output-dir=build/
```

This will prefix all vendor code and output the isolated plugin to the `build/` directory.

---

For more details, see the [php-scoper documentation](https://github.com/humbug/php-scoper).
