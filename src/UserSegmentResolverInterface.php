<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResult;
use Drupal\user\UserInterface;

/**
 * Decides whether a user belongs to a segment.
 *
 * resolve() returns allowed() or neutral() — never forbidden(). Forbidden is
 * reserved for a caller's own veto via UserSegmentAwareTrait::userIsNone(),
 * so one segment's "no" can never poison the orIf() chain inside userIsAny().
 * The returned AccessResult always carries the cacheability of the test, on
 * both the allowed and the neutral branch, so a negative answer never goes
 * stale.
 *
 * A site typically registers one implementation as a service, injected into
 * whatever `use`s UserSegmentAwareTrait via its abstract getSegmentResolver().
 *
 * @see \Drupal\user_ability\UserSegmentInterface
 * @see \Drupal\user_ability\UserSegmentAwareTrait
 */
interface UserSegmentResolverInterface {

  /**
   * Decides whether the given user belongs to the given segment.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user being classified.
   * @param \Drupal\user_ability\UserSegmentInterface $segment
   *   The segment being tested.
   * @param \Drupal\user_ability\AbilityContextInterface $context
   *   Whatever the resolver needs beyond the user, e.g. a group.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   allowed() or neutral() — never forbidden() — carrying the cacheability
   *   of the test on both branches.
   */
  public function resolve(UserInterface $user, UserSegmentInterface $segment, AbilityContextInterface $context): AccessResult;

}
