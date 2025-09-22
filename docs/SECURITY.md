# Security Notes

## Removal of phpinfo Endpoint

The standalone `phpinfo.php` endpoint and its associated stylesheet have been removed to avoid exposing detailed PHP environment information in production deployments. Ensure that the updated tree is deployed to prevent inadvertent leakage of server configuration data.
