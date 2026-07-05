<?php
/**
 * Config schema for the wordFilter module (namespace: modules.wordFilter.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{arrayField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Word filter',

	'FILTERS' => arrayField('Word filters', [
  '/\\b(rabi-en-rose|rabi~en~rose)\\b/i' => '<span class="rabienrose">Rabi~en~Rose</span>',
  '/\\b(newfag)\\b/i' => 'n00b like me',
  '/\\b(newfags)\\b/i' => 'n00bs like me',
  '/\\b(heyuri★cgi)\\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Heyuri%E2%98%85CGI">Heyuri★CGI</a>',
  '/\\b(heyuri cgi)\\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Heyuri%E2%98%85CGI">Heyuri★CGI</a>',
  '/\\b(chat@heyuri)\\b/i' => '<a href="https://cgi.heyuri.net/chat/">Chat@Heyuri</a>',
  '/\\b(polls@heyuri)\\b/i' => '<a href="https://cgi.heyuri.net/vote2/">Polls@Heyuri</a>',
  '/\\b(dating@heyuri)\\b/i' => '<a href="https://cgi.heyuri.net/dating/">Dating@Heyuri</a>',
  '/\\b(uploader@heyuri)\\b/i' => '<a href="https://up.heyuri.net/">Uploader@Heyuri</a>',
  '/@party 2/i' => '<a href="https://cgi.heyuri.net/party2/">@Party II</a>',
  '/@party ii/i' => '<a href="https://cgi.heyuri.net/party2/">@Party II</a>',
  '/\\b(ayashii world)\\b/i' => '<a href="https://wiki.heyuri.net/index.php?title=Ayashii_World">Ayashii World</a>',
  '/\\b(partybus)\\b/i' => '<span class="partybus"><span class="partybusColor1">p</span><span class="partybusColor2">a</span><span class="partybusColor3">r</span><span class="partybusColor4">t</span><span class="partybusColor5">y</span><span class="partybusColor6">b</span><span class="partybusColor7">u</span><span class="partybusColor8">s</span></span>',
  '/\\b(boku)\\b/i' => '<span class="boku" title="AGE OF DESU IS OVAR, WE BOKU NOW"><span class="bokuGreen">B</span><span class="bokuRed">O</span><span class="bokuGreen">K</span><span class="bokuRed">U</span></span>',
], 'JSON object of regex pattern => replacement HTML.'),
];
