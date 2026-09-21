<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
   * The result MUST carry cache metadata — ->cachePerPermissions() when
   * hasPermission() is a term, ->addCacheableDependency() for any context
   * dependency consulted, and ->setCacheMaxAge() for time-based abilities.
   * $user itself need not be added: AbilityChecker::check() adds it as a
   * cacheable dependency to every result, which is why this returns the
   * concrete AccessResult rather than AccessResultInterface. Note that
   * addCacheableDependency() contributes no cache tags for anonymous users
   * (uid 0 reports isNew() === TRUE), so a check whose answer can vary for
   * anonymous users must still call ->cachePerPermissions() or
   * ->cachePerUser().
   *
   * $context is always populated by this point — AbilityChecker substitutes
   * \Drupal\user_ability\NullContext() before calling in if the caller
   * passed none, so implementations never receive NULL here.
   */
  public function abilityAccess(UserInterface $user, AbilityContextInterface $context): AccessResult;

  /**
   * The concrete context class this check requires.
   *
   * AbilityChecker::check() verifies $context is an instance of this class
   * once, centrally, before calling abilityAccess() — implementations never
   * need to validate the context's shape themselves.
   *
   * Static, like getBusinessRule(): this describes the check's type, not an
   * instance, and must not vary by account or context.
   *
   * @return class-string<\Drupal\user_ability\AbilityContextInterface>
   */
  public static function getContextClass(): string;

  /**
   * A stakeholder-facing statement of the rule this check enforces.
   *
   * Written for whoever owns the business decision, not the developer who
   * implemented it: name the people/condition and the outcome — e.g.
   * "Managers can approve expense reports up to $500" or "Members must
   * complete onboarding before posting" — not the mechanics used to
   * enforce it (roles, permissions, entity grants, etc.). This is what
   * gets shown wherever abilities are documented or audited independent
   * of code, so a non-developer can confirm the system does what the
   * business agreed it should.
   *
   * Static: this describes the check's type, not an instance — it does
   * not depend on, and must not vary by, any account or context. A
   * TranslatableMarkup, not a plain string, because it is surfaced to end
   * users/administrators, not just read by developers.
   */
  public static function getBusinessRule(): TranslatableMarkup;

}
