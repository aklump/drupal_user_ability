<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResult;
use Drupal\user\UserInterface;

/**
 * Composes UserSegmentResolverInterface answers into grants and vetoes.
 *
 * PHP traits can't declare a constructor compatible with the class using
 * them, so — exactly like AbilityAwareTrait::getAbilityChecker() — the
 * collaborator arrives via an abstract getter the consuming class satisfies
 * however it already builds itself.
 */
trait UserSegmentAwareTrait {

  /**
   * Allows if the user is in ANY of these segments; neutral if in none.
   *
   * Evaluates in list order and returns on the first allow, so cheap
   * segments go first. Every segment actually consulted contributes its
   * cacheability, including on the "no" path — that is what keeps a
   * negative answer from going stale.
   *
   * Returns neutral rather than forbidden when nothing matches: this
   * grants, it does not veto, so it must never poison a caller's orIf().
   *
   * There is deliberately no userIsEvery(); see user_ability/AGENTS.md for
   * when and how to add one.
   *
   * @see self::userIsNone()
   */
  protected function userIsAny(UserInterface $user, AbilityContextInterface $context, UserSegmentInterface ...$segments): AccessResult {
    $result = AccessResult::neutral();
    foreach ($segments as $segment) {
      $result = $result->orIf($this->getSegmentResolver()->resolve($user, $segment, $context));
      if ($result->isAllowed()) {
        return $result;
      }
    }
    return $result;
  }

  /**
   * Vetoes if the user is in ANY of these segments; passes through otherwise.
   *
   * Returns allowed() rather than neutral() when the veto does not fire, so
   * ->andIf($nodeAccess) passes the node's own answer through unchanged —
   * neutral()->andIf($x) silently discards $x instead.
   */
  protected function userIsNone(UserInterface $user, AbilityContextInterface $context, UserSegmentInterface ...$segments): AccessResult {
    $result = $this->userIsAny($user, $context, ...$segments);
    if ($result->isAllowed()) {
      return AccessResult::forbidden()->inheritCacheability($result);
    }
    return AccessResult::allowed()->inheritCacheability($result);
  }

  /**
   * @return \Drupal\user_ability\UserSegmentResolverInterface
   *   The resolver to dispatch segment checks to.
   */
  abstract protected function getSegmentResolver(): UserSegmentResolverInterface;

}
