-- VARIABLES TO SET BEFORE RUNNING
SET @old_db = 'pixmicat';
SET @new_db = 'kokonotsuba';

SET @old_post_table = 'imglog';
SET @board_uid = 1;	-- Set this to the existing board UID of the board you're importing data to

SET @board_table = 'boards';
SET @thread_table = 'threads';
SET @post_table = 'posts';
SET @post_number_table = 'post_numbers';

--  Step 1: Insert Threads (OP posts only)
SET @sql1 := CONCAT(
	'INSERT INTO ', @new_db, '.', @thread_table, ' (
		thread_uid,
		post_op_number,
		post_op_post_uid,
		boardUID,
		thread_created_time,
		last_reply_time,
		last_bump_time
	)
	SELECT
		LPAD(p.no, 10, ''0''),
		p.no,
		0,
		', @board_uid, ',
		FROM_UNIXTIME(p.time),
		FROM_UNIXTIME(p.time),
		FROM_UNIXTIME(p.time)
	FROM ', @old_db, '.', @old_post_table, ' p
	LEFT JOIN ', @new_db, '.', @thread_table, ' t
		ON t.thread_uid = LPAD(p.no, 10, ''0'') AND t.boardUID = ', @board_uid, '
	WHERE p.resto = 0 AND t.thread_uid IS NULL;'
);
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- STEP 2: Insert all posts (OPs + replies)
SET @sql = CONCAT(
	'INSERT INTO ', @new_db, '.', @post_table, ' (
		no,
		boardUID,
		thread_uid,
		root,
		time,
		md5chksum,
		category,
		tim,
		fname,
		ext,
		imgw,
		imgh,
		imgsize,
		tw,
		th,
		pwd,
		now,
		name,
		email,
		sub,
		com,
		host,
		status
	)
	SELECT
		p.no,
		', @board_uid, ',
		LPAD(CASE WHEN p.resto = 0 THEN p.no ELSE p.resto END, 10, ''0''),
		IFNULL(p.root, FROM_UNIXTIME(0)),
		p.time,
		p.md5chksum,
		p.category,
		p.tim,
		CAST(p.tim AS CHAR),
		p.ext,
		p.imgw,
		p.imgh,
		p.imgsize,
		p.tw,
		p.th,
		p.pwd,
		p.now,
		p.name,
		p.email,
		p.sub,
		p.com,
		p.host,
		p.status
	FROM ', @old_db, '.', @old_post_table, ' p
	LEFT JOIN ', @new_db, '.', @post_table, ' np
		ON p.no = np.no AND np.boardUID = ', @board_uid, '
	WHERE np.no IS NULL;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- STEP 3: Update thread table to set post_op_post_uid
SET @sql = CONCAT(
	'UPDATE ', @new_db, '.', @thread_table, ' t
	JOIN ', @new_db, '.', @post_table, ' p
		ON t.thread_uid = p.thread_uid
		AND t.post_op_number = p.no
		AND t.boardUID = p.boardUID
	SET t.post_op_post_uid = p.post_uid
	WHERE t.boardUID = ', @board_uid, ' AND t.post_op_post_uid IS NULL;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- STEP 4: Insert post numbers into post_number table
SET @sql = CONCAT(
	'INSERT IGNORE INTO ', @new_db, '.', @post_number_table, ' (
		post_number,
		board_uid
	)
	SELECT
		no,
		', @board_uid, '
	FROM ', @old_db, '.', @old_post_table, ';'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;