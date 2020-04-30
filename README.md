# FTP script for M122
This project has been made for the TBZ Module M122.
It handles fictional invoice data and processes it.

## Installation
To install all dependencies run `composer install`.
Make sure to satisfy all php requirements, otherwise you  won't be able to install.

### Configuration
All necessary configuration can and should be done in the `.env` file.
Please refer to this file to see how to overwrite it and what has to be set.
Most of this configuration isn't validated, but you should still see the according errors in the error log.
The error log can be found in `var/log/$APP_ENV.log`.

## Usage
The app defines multiple command to handle the different parts of the process.
All this commands can be run on their own and don't directly rely on the others.
Although all commands are necessary to fulfil the job.

A list of the available commands:
* `app:invoices:import` does import/download the .data files from the FTP server.
* `app:invoices:process` processes the previous downloaded data and creates a txt invoice and a xml for the invoice system.
* `app:invoices:upload` uploads the previously generated files to the FTP server with the invoice system.
* `app:invoices:receipts` gathers the receipts which should have been generates by the invoice system with our previously uploaded data.
* `app:invoices:send` sends an email with the receipt and the txt compressed in a zip file. The zip file will also be uploaded the to FTP server.

This process can be automated by specify the commands as cron jobs.
