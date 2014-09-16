<?php

/**
 * @package PDFGenerator
 */

define('SUBM', 1);
define('NOTSUBM', 0);
define('LEADER', 1);
define('NOTLEADER', 0);

// PDF types
define('RPTP_NORMAL', 0);
define('RPTP_INPUT', 1);	// Not used yet.
define('RPTP_LEADER', 2);
define('RPTP_REVIEWER', 3);
define('RPTP_SUPPORTING', 4);
define('RPTP_EVALUATOR', 5);
define('RPTP_EVALUATOR_PROJ', 6);
define('RPTP_EVALUATOR_NI', 7);
define('RPTP_LEADER_COMMENTS', 8);
define('RPTP_EXIT_HQP', 9);
define('RPTP_HQP', 9); // Exit is not longer used, but they should be considered equivallent
define('RPTP_NI_COMMENTS', 10);
define('RPTP_HQP_COMMENTS', 11);
define('RPTP_LEADER_MILESTONES', 12);
define('RPTP_NI_PROJECT_COMMENTS', 13);
define('RPTP_LOI_REVIEW', 14);
define('RPTP_LOI_EVAL_REVIEW', 15);
define('RPTP_LOI_EVAL_FEEDBACK', 16);
define('RPTP_LOI_REV_REVIEW', 17);
define('RPTP_MTG', 18); // Mind The Gap
define('RPTP_CHAMP', 19);
define('RPTP_PROJECT_CHAMP', 20);
define('RPTP_PROJECT_ISAC', 21);
define('RPTP_SUBPROJECT', 22);

define('RPTP_NI_ZIP', 100);
define('RPTP_PROJ_ZIP', 101);
define('RPTP_HQP_ZIP', 102);

// Subject types (for RPTP_EVALUATOR reports)
define('EVTP_PERSON', 1);
define('EVTP_PROJECT', 2);

