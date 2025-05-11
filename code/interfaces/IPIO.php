<?php

/**
 * IPIO
 */
interface IPIO {

	/**
	 * Get the PIO module version.
	 *
	 * @return string PIO version information string
	 */
	public function pioVersion();

	/**
	 * Output post list
	 *
	 * @param  integer $resno   Thread number
	 * @param  integer $start   Start position
	 * @param  integer $amount  Number of posts
	 * @return array            Array of post numbers
	 */
	public function fetchPostList(mixed $resno = 0, int $start = 0, int $amount = 0, string $host = '');

	/**
	 * Output posts
	 *
	 * @param  mixed $postlist  Post number or array of post numbers
	 * @param  string $fields   Fields to output
	 * @return array            Array of post content
	 */
	public function fetchPosts($postlist, $fields = '*');

	/**
	 * Delete old attachments (outputs list of attachments)
	 *
	 * @param  int     $total_size   Current used size
	 * @param  int     $storage_max  Maximum storage limit
	 * @param  boolean $warnOnly     Only warn without deleting
	 * @return array                 Array of image files and thumbnails
	 */
	public function delOldAttachments($board, $total_size, $storage_max, $warnOnly = true);

	/**
	 * Delete posts
	 *
	 * @param  array $posts  Array of post numbers to delete
	 * @return array         Array of image files and thumbnails
	 */
	public function removePosts($posts);

	/**
	 * Delete attachments (outputs list of attachments)
	 *
	 * @param  array   $posts     Array of post numbers to delete
	 * @param  boolean $recursion Recursively find related posts and replies
	 * @return array              Array of image files and thumbnails
	 */
	public function removeAttachments($posts, $recursion = false);

	/**
	 * Add a new post/reply
	 *
	 * @param board   $board     Board being posted to
	 * @param int     $no        Post number
	 * @param string  $thread_uid Uid of the parent thread
	 * @param int	  $post_position Position of the post in the thread
	 * @param bool	  $is_op	 if the post is OP
	 * @param string  $md5chksum Attachment image MD5
	 * @param string  $category  Category
	 * @param int	  $tim       Timestamp
	 * @param string  $ext       Attachment file extension
	 * @param int     $imgw      Image width
	 * @param int     $imgh      Image height
	 * @param string  $imgsize   Image file size
	 * @param int     $tw        Thumbnail width
	 * @param int     $th        Thumbnail height
	 * @param string  $pwd       Password
	 * @param string  $now       Post time string
	 * @param string  $name      Name
	 * @param string  $tripcode  Tripcode
	 * @param string  $secure_tripcode  Secure tripcode
	 * @param string  $capcode   Capcode
	 * @param string  $email     Email
	 * @param string  $sub       Title
	 * @param string  $com       Comment
	 * @param string  $host      Host name
	 * @param boolean $age       Bump thread
	 * @param string  $status    Status flag
	 */
	public function addPost(board $board, int $no, string $thread_uid, int $post_position, bool $is_op, string $md5chksum, string $category, int $tim, string $fname, string $ext, int $imgw, int $imgh, 
		string $imgsize, int $tw, int $th, string $pwd, string $now, string $name, string $tripcode, string $secure_tripcode, string $capcode, string $email, string $sub, string $com, string $host, bool $age = false, string $status = '');

	/**
	 * Check for successive posts
	 *
	 * @param  int     $lcount      Number of posts to check
	 * @param  string  $com         Comment
	 * @param  int     $timestamp   Post timestamp
	 * @param  string  $pass        Password
	 * @param  string  $passcookie  Cookie password
	 * @param  string  $host        Host name
	 * @param  boolean $isupload    Whether an image was uploaded
	 * @return boolean              Whether it's a successive post
	 */
	public function isSuccessivePost($board, $lcount, $com, $timestamp, $pass,
		$passcookie, $host, $isupload);

	/**
	 * Check for duplicate attachments
	 *
	 * @param  int     $lcount   Number of posts to check
	 * @param  string  $md5hash  MD5 hash
	 * @return boolean           Whether it's a duplicate image
	 */
	public function isDuplicateAttachment($board, $lcount, $md5hash);

	/**
	 * Search posts
	 *
	 * @param  array  $keyword  Array of keywords
	 * @param  string $field    Field
	 * @param  string $method   Search method
	 * @return array            Array of post content
	 */
	public function searchPosts(IBoard $board, array $keywords, string $field = 'com', string $method = 'OR', int $limit = 20, int $offset = 0): array;

	/**
	 * Search category tags
	 *
	 * @param  string $category Category
	 * @return array            Array of post numbers under this category
	 */
	public function searchCategory($category);

	/**
	 * Get post status
	 *
	 * @param  string $status  Status flag
	 * @return FlagHelper      Flag status modification object
	 */
	public function getPostStatus($status);

	/**
	 * Update post
	 *
	 * @param int   $no         Post number
	 * @param array $newValues  Array of new field values
	 */
	public function updatePost($no, $newValues);

	/**
	 * Set post status
	 *
	 * @param int $no         Post number
	 */
	public function setPostStatus($no, $newStatus);

	/**
	 * Get the IP of the post's OP
	 *
	 * @param int $no         Post number
	 */
	public function getPostIP($no);
}
