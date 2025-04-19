<?php

/*
 * Constants and enums for Kokonotsuba!
 *
 * This file is strictly for constants or unchanging/non-configurable
 * values that are to be accessible globally, regardless of board or
 * configuration. Do not add configurations to this file.
 */
namespace Kokonotsuba\Root\Constants;


/* Constants */
const GLOBAL_BOARD_UID = -1; // number that corrosponds to all boards


/* Enums */
// to be implemented
enum userRole: string {
    case LEV_ADMIN = 4;
    case LEV_MOD = 3;
    case LEV_JANITOR = 2;
    case LEV_USER = 1;
    case LEV_NONE = 0;
}
