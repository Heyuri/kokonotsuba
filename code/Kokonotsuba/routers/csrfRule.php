<?php

namespace Kokonotsuba\routers;

/**
 * What modeHandler checks before a route runs.
 */
enum csrfRule {
	/** The route checks for itself, or takes posts from users without a session token. */
	case NONE;

	/** GET still draws the page; any POST must carry a valid token. */
	case ON_POST;

	/** GET is refused with a 405; the POST must carry a valid token. */
	case POST_ONLY;
}
