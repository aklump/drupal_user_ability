<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\user\UserInterface;

/**
 * Implemented by whatever decides one ability.
 *
 * This is where permissions, roles, group membership, ownership, and any
 * other signals get composed into a single yes/no answer for one ability.
 */
interface AbilityCheckInterface {

  /**
   * Decides whether the given user has the ability this check implements.
   *
   * The result MUST carry cache metadata — at minimum
   * ->addCacheableDependency($user), plus ->cachePerPermissions() when
   * hasPermission() is a term, ->addCacheableDependency() for any context
   * dependency consulted, and ->setCacheMaxAge() for time-based abilities.
   * Note that addCacheableDependency() contributes no cache tags for
   * anonymous users (uid 0 reports isNew() === TRUE), so a check whose
   * answer can vary for anonymous users must also call
   * ->cachePerPermissions() or ->cachePerUser().
   *
   * $context is always populated by this point — AbilityChecker
   * substitutes getDefaultContext() before calling in if the caller passed
   * none, so implementations never receive NULL here.
   */
  public function abilityAccess(UserInterface $user, AbilityContextInterface $context): AccessResultInterface;

  /**
   * The context to use when a caller supplies none.
   *
   * Every implementer answers this explicitly, even trivially (e.g.
   * returning a context with all-NULL properties), so the dispatcher never
   * needs to guess.
   */
  public function getDefaultContext(): AbilityContextInterface;

}
