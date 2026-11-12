-- Faculty Opportunities Portal v1.0
-- SQL REFERENCE / READ-ONLY EXAMPLES (NOT AN INSTALLER)
-- WordPress already creates all these tables. Do NOT CREATE or replace existing WordPress tables.
-- Replace wp_ with your installation's actual $table_prefix before running any query.
-- These queries are informational and are not needed for plugin activation.

-- 1) Ten most recent published opportunities (front-page source)
SELECT p.ID, p.post_title, p.post_author, p.post_date, u.display_name AS professor_name
FROM wp_posts AS p
LEFT JOIN wp_users AS u ON u.ID = p.post_author
WHERE p.post_type = 'fcp_opportunity' AND p.post_status = 'publish'
ORDER BY p.post_date DESC, p.ID DESC
LIMIT 10;

-- 2) Published opportunities grouped by professor
SELECT u.ID AS professor_id, u.display_name,
       COUNT(p.ID) AS published_opportunities
FROM wp_users AS u
LEFT JOIN wp_posts AS p
       ON p.post_author = u.ID
      AND p.post_type = 'fcp_opportunity'
      AND p.post_status = 'publish'
GROUP BY u.ID, u.display_name
ORDER BY published_opportunities DESC;

-- 3) Fields associated with opportunities
SELECT p.ID AS opportunity_id, p.post_title, pm.meta_key, pm.meta_value
FROM wp_posts AS p
JOIN wp_postmeta AS pm ON pm.post_id = p.ID
WHERE p.post_type = 'fcp_opportunity' AND pm.meta_key LIKE '\_fcp\_%'
ORDER BY p.ID DESC, pm.meta_key;

-- 4) Category assignments
SELECT p.ID AS opportunity_id, p.post_title, t.name AS category_name
FROM wp_posts AS p
JOIN wp_term_relationships AS r ON r.object_id = p.ID
JOIN wp_term_taxonomy AS tt ON tt.term_taxonomy_id = r.term_taxonomy_id
JOIN wp_terms AS t ON t.term_id = tt.term_id
WHERE p.post_type = 'fcp_opportunity' AND tt.taxonomy = 'fcp_category'
ORDER BY p.ID DESC;

-- 5) Applications per opportunity (v2)
SELECT p.ID, p.post_title, COUNT(a.id) AS applications
FROM wp_posts AS p
LEFT JOIN wp_fcp_applications AS a ON a.opportunity_id = p.ID
WHERE p.post_type = 'fcp_opportunity'
GROUP BY p.ID, p.post_title
ORDER BY applications DESC;

-- 6) New applications waiting for a professor
SELECT a.id, a.student_name, a.student_email, a.created_at
FROM wp_fcp_applications AS a
WHERE a.professor_id = 2 AND a.status = 'new'   -- replace 2 with the professor's user ID
ORDER BY a.created_at DESC;

-- 7) Published advice with audience, topic and helpful votes (v2.1)
SELECT p.ID, p.post_title, u.display_name AS professor,
       aud.meta_value AS audience, tp.meta_value AS topic,
       (SELECT COUNT(*) FROM wp_fcp_advice_votes v WHERE v.advice_id = p.ID) AS helpful_votes
FROM wp_posts AS p
LEFT JOIN wp_users AS u ON u.ID = p.post_author
LEFT JOIN wp_postmeta AS aud ON aud.post_id = p.ID AND aud.meta_key = '_fcp_audience'
LEFT JOIN wp_postmeta AS tp  ON tp.post_id  = p.ID AND tp.meta_key  = '_fcp_topic'
WHERE p.post_type = 'fcp_advice' AND p.post_status = 'publish'
ORDER BY helpful_votes DESC, p.post_date DESC;

-- 8) Advice waiting for admin approval (v2.1)
SELECT p.ID, p.post_title, p.post_date, u.display_name AS professor
FROM wp_posts AS p
LEFT JOIN wp_users AS u ON u.ID = p.post_author
WHERE p.post_type = 'fcp_advice' AND p.post_status = 'pending'
ORDER BY p.post_date ASC;
