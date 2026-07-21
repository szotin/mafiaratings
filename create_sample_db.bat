mysql -u root < db\create_sample_db.sql
if not exist include\api_keys.php copy include\api_empty_keys.php include\api_keys.php