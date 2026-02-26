# Upgrading

## Upgrading to version 1.2.0

Manual AWS SDK installations are no longer supported due to missing version constraints.
**Composer is now the only supported installation method.**
Additionally, the expected installation path has changed — any existing AWS SDK installation,
whether manual or via Composer, will be ignored, requiring a fresh installation.

Run the following commands in the module directory to remove previous installations
and install with appropriate version constraints:

```bash
rm -rf library/vendor
composer install --no-dev --optimize-autoloader
```
