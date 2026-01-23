<?php

class postSearchService {
	public function __construct(
		private readonly postSearchRepository $postSearchRepository
	) {}

	public function searchPosts(
		array $stopWords, 
		array $fields, 
		array $boardUids, 
		bool $matchWholeWords, 
		int $page = 0, 
		int $postsPerPage = 20
	): ?array {
		// sanitize fields
		$fields = $this->sanitizeFields($fields);

		// tokenize and compile each field for boolean full-text search
		foreach ($fields as $field => $value) {
			// dont parse post number
			if($field === 'no') {
				continue;
			}

			$fields[$field] = parseToBooleanFulltext($value, $matchWholeWords, $stopWords);
		}

		// calculate pagination parameters
		$offset = $page * $postsPerPage;

		return $this->searchByFullText($fields, $boardUids, $postsPerPage, $offset);
	}

	private function sanitizeFields(array $fields): array {
		// Define allowed fields
		$allowedFields = [
			// general, searches all text fields
			'general', 

			// comment field
			'com', 
			
			// name field
			'name', 
			
			// email field
			'email',
			
			// subject field
			'sub', 
			
			// post number
			'no', 
			
			// file name field for any files attached to the post
			'file_name', 
			
			// timestamp of the post
			'root'
		];

		// Remove any fields that are not allowed
		$fields = array_intersect_key($fields, array_flip($allowedFields));

		// loop through and remove empty fields
		$fields = array_filter($fields, fn($field) => !empty($field));

		return $fields;
	}

	private function searchByFullText(array $fields, array $boardUids, int $limit, int $offset): ?array {
		$posts = $this->postSearchRepository->fetchPostsByFullText($fields, $boardUids, $limit, $offset);
		$count = $this->postSearchRepository->countPostsByFullText($fields, $boardUids);

		// no posts found - return null
		if(!$posts || $count === 0) {
			return null;
		}

		return $this->formatResults($posts, $count);
	}

	private function formatResults(array $posts, int $totalPostCount): array {
		$results = [];
		foreach ($posts as $post) {
			$post_uid = $post['post_uid'];

			$results[$post_uid] = [
				'post' => $post,
			];
		}
		return ['results_data' => $results, 'total_posts' => $totalPostCount];
	}
}