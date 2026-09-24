<?php

namespace Drupal\user_ability;

/**
 * Implemented by a site's segment enum — a fixed set of named user buckets.
 *
 * A segment classifies a user into a named bucket for use in an access
 * decision — "leader", "administrator", "buyer" — the way `AbilityInterface`
 * names a business capability rather than a mechanism. A bare marker, like
 * `AbilityInterface`: an enum implementing this interface needs no method
 * beyond what the enum already gives for free.
 *
 * Example implementation:
 * @code
 * enum SiteSegment implements UserSegmentInterface {
 *   case Leader;
 *   case Administrator;
 *   case Buyer;
 *   case Seller;
 * }
 * @endcode
 *
 * @see \Drupal\user_ability\UserSegmentResolverInterface
 * @see \Drupal\user_ability\UserSegmentAwareTrait
 */
interface UserSegmentInterface {}
