<?php
echo password_hash('admin@12345', PASSWORD_BCRYPT, ['cost' => 12]);