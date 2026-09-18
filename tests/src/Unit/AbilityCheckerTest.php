<?php

namespace Drupal\Tests\user_ability\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user_ability\AbilityChecker;
use Drupal\user_ability\AbilityCheckInterface;
use Drupal\user_ability\AbilityContextInterface;
use Drupal\user_ability\AbilityInterface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\user_ability\AbilityChecker
 * @group user_ability
 */
class AbilityCheckerTest extends UnitTestCase {

  /**
   * Builds an AbilityChecker whose toUser() is overridden for testability.
   *
   * Drupal\user\Entity\User::load()/::getAnonymousUser() are static calls
   * into the full entity/database stack and are out of reach of a pure
   * unit test. AbilityChecker::toUser() is protected specifically so a
   * test double can substitute a canned UserInterface (or NULL, to
   * simulate a vanished account) without touching those statics.
   */
  private function checkerWithUser(LoggerInterface $logger, ?UserInterface $user): AbilityChecker {
    return new class($logger, $user) extends AbilityChecker {

      public function __construct(LoggerInterface $logger, private readonly ?UserInterface $user) {
        parent::__construct($logger);
      }

      protected function toUser(AccountInterface $account): ?UserInterface {
        return $this->user;
      }

    };
  }

  /**
   * @covers ::check
   * @covers ::addCheck
   */
  public function testCheckDispatchesToRegisteredCheckWithExplicitContext(): void {
    $user = $this->createMock(UserInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $context = new FixtureContext();
    $expected = AccessResult::allowed();

    $check = $this->createMock(AbilityCheckInterface::class);
    $check->expects($this->once())
      ->method('abilityAccess')
      ->with($user, $context)
      ->willReturn($expected);
    $check->expects($this->never())->method('getDefaultContext');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('warning');

    $checker = $this->checkerWithUser($logger, $user);
    $checker->addCheck(FixtureAbility::class . '::' . FixtureAbility::DoThing->name, $check);

    $result = $checker->check(FixtureAbility::DoThing, $account, $context);
    $this->assertSame($expected, $result);
  }

  /**
   * @covers ::check
   */
  public function testCheckSubstitutesDefaultContextWhenNoneGiven(): void {
    $user = $this->createMock(UserInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $default_context = new FixtureContext();
    $expected = AccessResult::forbidden();

    $check = $this->createMock(AbilityCheckInterface::class);
    $check->expects($this->once())
      ->method('getDefaultContext')
      ->willReturn($default_context);
    $check->expects($this->once())
      ->method('abilityAccess')
      ->with($user, $default_context)
      ->willReturn($expected);

    $logger = $this->createMock(LoggerInterface::class);

    $checker = $this->checkerWithUser($logger, $user);
    $checker->addCheck(FixtureAbility::class . '::' . FixtureAbility::DoThing->name, $check);

    $result = $checker->check(FixtureAbility::DoThing, $account);
    $this->assertSame($expected, $result);
  }

  /**
   * @covers ::check
   */
  public function testCheckDeniesAndLogsWhenNoCheckRegistered(): void {
    $account = $this->createMock(AccountInterface::class);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with($this->stringContains('No ability check registered'), $this->anything());

    // No check registered at all — toUser() should never even be consulted.
    $checker = $this->checkerWithUser($logger, NULL);

    $result = $checker->check(FixtureAbility::DoThing, $account);
    $this->assertInstanceOf(AccessResultInterface::class, $result);
    $this->assertFalse($result->isAllowed());
  }

  /**
   * @covers ::check
   */
  public function testCheckDeniesAndLogsWhenAccountNoLongerResolves(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);

    $check = $this->createMock(AbilityCheckInterface::class);
    $check->expects($this->never())->method('abilityAccess');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with($this->stringContains('no longer exists'), $this->anything());

    // NULL simulates a deleted account: toUser() found nothing to load.
    $checker = $this->checkerWithUser($logger, NULL);
    $checker->addCheck(FixtureAbility::class . '::' . FixtureAbility::DoThing->name, $check);

    $result = $checker->check(FixtureAbility::DoThing, $account);
    $this->assertFalse($result->isAllowed());
  }

}

/**
 * A throwaway ability, declared for this test only — no Commons imports.
 */
enum FixtureAbility implements AbilityInterface {
  case DoThing;

  public function jsonSerialize(): mixed {
    return $this->name;
  }
}

/**
 * A throwaway context, declared for this test only — no Commons imports.
 */
final class FixtureContext implements AbilityContextInterface {}
