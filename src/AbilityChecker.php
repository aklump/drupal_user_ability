<?php

namespace Drupal\user_ability;

use Drupal\Core\Access\AccessResult;
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
class AbilityChecker implements AbilityCheckerInterface {

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
    if (str_starts_with($key, '\\')) {
      throw new \InvalidArgumentException('Ability key must not start with a backslash');
    }
    $this->checks[$key] = $check;
  }

  /**
   * {@inheritdoc}
   */
  public function check(AbilityInterface $ability, AccountInterface $account, ?AbilityContextInterface $context = NULL): AccessResult {
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

      // The answer is about this one account, so it must not be cached as
      // if it applied to everyone.
      return AccessResult::forbidden()->setCacheMaxAge(0);
    }

    $context ??= new NullContext();
    $requiredClass = $check->getContextClass();
    if (!$context instanceof $requiredClass) {
      throw new \InvalidArgumentException(sprintf(
        'Ability check %s requires %s, got %s.',
        $check::class, $requiredClass, $context::class,
      ));
    }

    return $check->abilityAccess($user, $context)->addCacheableDependency($user);
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
