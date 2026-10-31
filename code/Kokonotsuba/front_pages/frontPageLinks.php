<?php

namespace Kokonotsuba\front_pages;

/** URLs into the front page, built on HOME (where the front page is served). */
final class frontPageLinks {
	public function __construct(private readonly string $home) {}

	public function home(): string {
		return $this->home;
	}

	public function page(string $slug): string {
		return $this->with(['p' => $slug]);
	}

	public function news(int $id): string {
		return $this->with(['news' => $id]);
	}

	public function newsPage(int $page): string {
		return $page <= 1 ? $this->home : $this->with(['page' => $page]);
	}

	/** A module's front page (abstractModuleFront::renderModulePage()). */
	public function module(string $moduleName): string {
		return $this->with(['module' => $moduleName]);
	}

	public function admin(string $section = ''): string {
		return $this->with(['mode' => 'admin']) . ($section !== '' ? '#' . $section : '');
	}

	public function logo(): string {
		return $this->with(['mode' => 'logo']);
	}

	/** The news editor; a new entry without an id. */
	public function editNews(?int $id = null): string {
		return $this->with($id === null ? ['mode' => 'editNews'] : ['mode' => 'editNews', 'id' => $id]);
	}

	/** The page editor; a new page without an id. */
	public function editPage(?int $id = null): string {
		return $this->with($id === null ? ['mode' => 'editPage'] : ['mode' => 'editPage', 'id' => $id]);
	}

	/** @param array<string, scalar> $query */
	public function with(array $query): string {
		$base = strtok($this->home, '#');

		return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($query);
	}
}
