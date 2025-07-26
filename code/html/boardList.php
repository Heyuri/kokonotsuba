<?php

//for the board filter form
function generateBoardListCheckBoxHTML(board $currentBoard, array $filterBoard, array $boards, bool $selectAll = false) {
	$listHTML = '';

	foreach($boards as $board) {
		$boardTitle = $board->getBoardTitle();
		$boardUID = htmlspecialchars($board->getBoardUID());
		
		$isChecked = $selectAll || in_array($boardUID, $filterBoard) || ($boardUID === $currentBoard->getBoardUID() && empty($filterBoard));
			
		$listHTML .= '<li><label class="filterSelectBoardItem"><input name="board[]" type="checkbox" value="' . $boardUID . '" ' . ($isChecked ? 'checked' : '') . '>' . $boardTitle . '</label></li>';
	}
	
	return $listHTML;
}

//for the rebuild action form
function generateRebuildListCheckboxHTML(array $boards) {
	$listHTML = '<ul class="filterSelectBoardList">';

	foreach($boards as $board) {
		$boardTitle = $board->getBoardTitle();
		$boardUID = htmlspecialchars($board->getBoardUID());
		
		$listHTML .= '<li><label class="filterSelectBoardItem"><input name="rebuildBoardUIDs[]" type="checkbox" value="' . $boardUID . '" checked>' . $boardTitle . '</label></li>';
	}
	
	$listHTML .= '</ul>';
	
	return $listHTML;
}
	
//for the move_thread form
function generateBoardListRadioHTML(board $currentBoard, array $boards) {
	$listHTML = '';
	
	foreach($boards as $board) {
		if($currentBoard && $board->getBoardUID() === $currentBoard->getBoardUID()) continue;
			
		$boardTitle = $board->getBoardTitle();
		$boardUID = htmlspecialchars($board->getBoardUID());
			
		$listHTML .= '<label> <input name="radio-board-selection" type="radio" value="' . $boardUID . '">'.$boardTitle.'</label>  ';
	}
		
	return $listHTML;
}