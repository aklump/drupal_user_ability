<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Dispatches an ability to its registered check.
 *
 * This is the one place a NULL context is resolved and the one place an
 * ability maps to its check. Checks register themselves via addCheck(),
 * called by AbilityCheckCollectorPass at container-build time — nothing
 * here knows, or needs to know, which checks exist.
 *
 * Not declared final: toUser() is protected, not private, specifically so
 * a unit test can substitute a canned UserInterface (or NULL) without a
 * kernel-level Drupal bootstrap — Drupal\user\Entity\User::load() and
 * ::getAnonymousUser() are static calls this class deliberately isolates
 * to one method, but they still can't be exercised from a pure unit test.
 */
class AbilityChecker {

  /**
   * Registered checks, keyed by fully-qualified ability case reference.
   *
   * @var array<string, \Drupal\user_ability\AbilityCheckInterface>
   */
  private array $checks = [];

  public function __construct(
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Registers a check for a given ability key.
   *
   * @param string $key
   *   The fully-qualified case reference, e.g.
   *   'Drupal\my_module\Enum\Ability::DoThing'.
   * @param \Drupal\user_ability\AbilityCheckInterface $check
   *   The check to dispatch to for this ability.
   */
  public function addCheck(string $key, AbilityCheckInterface $check): void {
    $this->checks[$key] = $check;
  }

  /**
   * Decides whether the given account has the given ability.
   *
   * @param \Drupal\user_ability\AbilityInterface $ability
   *   The ability being asked about.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account asking. Normalized once to \Drupal\user\UserInterface
   *   before being handed to the check.
   * @param \Drupal\user_ability\AbilityContextInterface|null $context
   *   What is being asked about, beyond the account. NULL to let the check
   *   supply its own default via getDefaultContext().
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The cache-aware access result. AccessResult::forbidden() when no
   *   check is registered for the ability, or when the account no longer
   *   resolves to a real user — both cases are logged, never thrown.
   */
  public function check(AbilityInterface $ability, AccountInterface $account, ?AbilityContextInterface $context = NULL): AccessResultInterface {
    $key = $ability::class . '::' . $ability->name;
    $check = $this->checks[$key] ?? NULL;
    if (!$check) {
      $this->logger->warning('No ability check registered for @ability.', [
        '@ability' => $key,
      ]);

      return AccessResult::forbidden();
    }

    $user = $this->toUser($account);
    if (!$user) {
      $this->logger->warning('Account @uid no longer exists.', [
        '@uid' => $account->id(),
      ]);

      return AccessResult::forbidden();
    }

    return $check->abilityAccess($user, $context ?? $check->getDefaultContext());
  }

  /**
   * Normalizes an account to a loaded user entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   The anonymous user entity for an anonymous account, the loaded user
   *   entity for an authenticated one, or NULL when the account no longer
   *   resolves to a real user (e.g. deleted mid-request).
   */
  protected function toUser(AccountInterface $account): ?UserInterface {
    if ($account->isAnonymous()) {
      return User::getAnonymousUser();
    }

    return User::load($account->id());
  }

}
