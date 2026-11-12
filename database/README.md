# Reconstructed local database

In phpMyAdmin, select `online_medical_protal`, choose **Import**, select
`database/online_medical_protal.sql`, and click **Go**. The script creates seven
tables without dropping existing data. It does not migrate existing tables with
different structures. Create accounts using the application's registration pages;
no default accounts or patient records are included.

From the project root run `php -S localhost:8000` and visit
http://localhost:8000/html/index.php. MySQL must be running with the connection
settings used in the PHP files: localhost, root, empty password.

This schema was inferred from source, not recovered from an original export.
Generated `id` and `name` aliases support inconsistent column names in the account
pages. Relationships are indexed without foreign-key constraints because existing
delete handlers do not clean up dependent records. The payment date remains text
to match the format written by `html/migara/save.php`.

Known application limitations outside this database reconstruction:

- Registration/login use MD5 passwords, while admin-created accounts use SHA-1.
  Accounts created through admin forms cannot log in through the main login page
  until the PHP password handling is made consistent.
- Older pages (`html/ishara/my.php`, `html/ishara/veiw.php`, and
  `html/oshadhi/adfov.php`) use obsolete column/table names or the separate
  `favouritedoc` database. The main flow uses `doctor` and `favourite`; those older
  pages require PHP updates.
- Some pages have independent session, validation, and redirect issues. Creating
  tables does not establish that every workflow works.
- Use synthetic data only. The existing payment handler stores full card numbers
  and CVVs, and account queries use weak hashes and SQL string interpolation.
  This project requires security changes before use with real users or payments.
