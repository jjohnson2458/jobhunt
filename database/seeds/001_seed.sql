-- Default admin user (password: 24AdaPlace) and initial job tracks
-- Run after 001_init.sql

INSERT IGNORE INTO users (id, email, password_hash, first_name, last_name, role)
VALUES (1, 'email4johnson@gmail.com',
  '$2y$10$REPLACE_WITH_HASH_FROM_INSTALL_SCRIPT',
  'J.J.', 'Johnson', 'admin');

INSERT IGNORE INTO job_tracks (name, slug, role_keywords, exclude_keywords, salary_floor, locations, remote_ok, resume_template, cover_letter_tone, notes)
VALUES
  ('Software', 'software',
   'LAMP, PHP, Magento, Laravel, MySQL, REST API, AWS, full stack, senior developer, contract',
   'unpaid, commission only, sales, .net, c#',
   75000,
   'Western NY, Buffalo, Rochester, remote',
   1,
   'docs/TEMPLATE_SOFTWARE_RESUME.docx',
   'professional-contract',
   'Senior contract LAMP developer. Prefer contract over FTE.'),
  ('Electric', 'electric',
   'electrician, residential electrical, electrical repair, journeyman, helper',
   'commission only, door to door',
   40000,
   'Western NY, Buffalo',
   0,
   'docs/TEMPLATE_ELECTRIC_RESUME.docx',
   'practical-blue-collar',
   'Residential electrical work. References available.');

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('site_name', 'Foot Traffic Analytics'),
  ('owner_name', 'J.J. Johnson'),
  ('owner_email', 'email4johnson@gmail.com'),
  ('claude_model', 'claude-sonnet-4-20250514'),
  ('scraper_user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
