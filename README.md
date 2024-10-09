## Introduction
This a base for Debricked's backend home task. It provides a Symfony skeleton and a Docker environment with a few handy 
services:

- RabbitMQ
- MySQL (available locally at 3307, between Docker services at 3306)
- MailHog (UI available locally at 8025)
- PHP
- Nginx (available locally at 8888, your API endpoints will accessible through here)

See .env for working credentials for RabbitMQ, MySQL and MailHog.

A few notes:
- By default, emails sent through Symfony Mailer will be sent to MailHog, regardless of recipient.

## How to use the Docker environment
### Starting the environment
`docker compose up`

### Stopping the environment
`docker compose down`

### Running PHP based commands
You can access the PHP environment's shell by executing `docker compose exec php bash` (make sure the environment is up 
and running before, or the command will fail) in root folder.

We recommend that you always use the PHP container's shell whenever you execute PHP, such as when installing and 
requiring new composer dependencies.

## Automatic User Notification on Scan Completion

When the scan has been completed, you should automatically notify the user based on a set of pre-defined rules. This is achieved by running a Symfony Command in the background inside the Docker container. The rules consist of triggers and actions that result from those triggers. The triggers could include:

- **File Upload Completion:** Notify when a file upload is successfully completed.
- **Scan Result Availability:** Notify when the results of a scan are available.
- **Error Detection:** Notify if an error occurs during the scanning process.

These rules are hard-coded into the application and are designed to ensure users are informed of important events in a timely manner.

To execute the Symfony Command for checking scan status, use the following command:
