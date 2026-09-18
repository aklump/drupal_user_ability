<?php

namespace Drupal\user_ability;

/**
 * Marker for whatever a site's checks need beyond the account.
 *
 * Deliberately empty — a group, a node, a taxonomy term are all site
 * concepts, not generic ones. Each site declares its own concrete shape
 * implementing this interface.
 */
interface AbilityContextInterface {}
