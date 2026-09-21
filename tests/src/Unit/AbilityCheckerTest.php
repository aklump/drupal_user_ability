<?php

namespace Drupal\Tests\user_ability\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user_ability\AbilityChecker;
use Drupal\user_ability\AbilityCheckInterface;
use Drupal\user_ability\AbilityContextInterface;
use Drupal\user_ability\NullContext;
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

    // FixtureCheckInterface::getContextClass() is static, and PHPUnit's mock
    // generator unconditionally throws for any static method invoked on a
    // createMock()'d object (see mocked_static_method.tpl) — so a hand-written
    // fixture double is used here instead of createMock().
    $check = new FixtureCheck(FixtureContext::class, $expected);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('warning');

    $checker = $this->checkerWithUser($logger, $user);
    $checker->addCheck(FixtureAbility::class . '::' . FixtureAbility::DoThing->name, $check);

    $result = $checker->check(FixtureAbility::DoThing, $account, $context);
    $this->assertSame($expected, $result);
    $this->assertSame(1, $check->callCount);
    $this->assertSame($user, $check->receivedUser);
    $this->assertSame($context, $check->receivedContext);
  }

  /**
   * @covers ::check
   */
  public function testCheckSubstitutesDefaultContextWhenNoneGiven(): void {
    $user = $this->createMock(UserInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $expected = AccessResult::forbidden();

    $check = new FixtureCheck(NullContext::class, $expected);

    $logger = $this->createMock(LoggerInterface::class);

    $checker = $this->checkerWithUser($logger, $user);
    $checker->addCheck(FixtureAbility::class . '::' . FixtureAbility::DoThing->name, $check);

    $result = $checker->check(FixtureAbility::DoThing, $account);
    $this->assertSame($expected, $result);
    $this->assertSame(1, $check->callCount);
    $this->assertEquals(new NullContext(), $check->receivedContext);
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

  /**
   * @covers ::check
   */
  public function testCheckThrowsWhenContextDoesNotMatchRequiredClass(): void {
    $user = $this->createMock(UserInterface::class);
    $account = $this->createMock(AccountInterface::class);

    $check = new FixtureCheck(FixtureContext::class, AccessResult::allowed());

    $logger = $this->createMock(LoggerInterface::class);

    $checker = $this->checkerWithUser($logger, $user);
    $checker->addCheck(FixtureAbility::class . '::' . FixtureAbility::DoThing->name, $check);

    $this->expectException(\InvalidArgumentException::class);
    try {
      $checker->check(FixtureAbility::DoThing, $account, new NullContext());
    }
    finally {
      $this->assertSame(0, $check->callCount);
    }
  }

}

/**
 * A throwaway ability, declared for this test only since consumers define.
 */
enum FixtureAbility implements AbilityInterface {

  case DoThing;

  public function jsonSerialize(): mixed {
    return $this->name;
  }

}

/**
 * A throwaway context, declared for this test only since consumers define
 * their own concrete AbilityContextInterface shapes.
 */
final class FixtureContext implements AbilityContextInterface {}

/**
 * A throwaway check, declared for this test only.
 *
 * A hand-written double rather than createMock(AbilityCheckInterface::class)
 * because getContextClass() is static, and PHPUnit's mock generator
 * unconditionally throws BadMethodCallException for any static method
 * invoked on a mocked object — static methods cannot be stubbed.
 */
final class FixtureCheck implements AbilityCheckInterface {

  public int $callCount = 0;

  public ?UserInterface $receivedUser = NULL;

  public ?AbilityContextInterface $receivedContext = NULL;

  private static string $activeContextClass = FixtureContext::class;

  public function __construct(
    string $contextClass,
    private readonly AccessResultInterface $result,
  ) {
    // Instance state can't back a static method, so the class-string each
    // test wants getContextClass() to return is stashed here instead.
    self::$activeContextClass = $contextClass;
  }

  public static function getContextClass(): string {
    return self::$activeContextClass;
  }

  public function abilityAccess(UserInterface $user, AbilityContextInterface $context): AccessResultInterface {
    $this->callCount++;
    $this->receivedUser = $user;
    $this->receivedContext = $context;

    return $this->result;
  }

  public static function getBusinessRule(): TranslatableMarkup {
    return new TranslatableMarkup('Fixture business rule.');
  }

}
