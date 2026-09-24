<?php

namespace Drupal\Tests\user_ability\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user_ability\AbilityContextInterface;
use Drupal\user_ability\UserSegmentAwareTrait;
use Drupal\user_ability\UserSegmentInterface;
use Drupal\user_ability\UserSegmentResolverInterface;

/**
 * @coversDefaultClass \Drupal\user_ability\UserSegmentAwareTrait
 * @group user_ability
 */
class UserSegmentAwareTraitTest extends UnitTestCase {

  /**
   * Builds a user double whose cache metadata is well-formed.
   *
   * CacheableDependencyInterface's getters carry no native return types, so
   * an unstubbed mock returns NULL from them, which
   * ->addCacheableDependency($user) would then fail to merge.
   */
  private function mockUser(array $cacheTags = []): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('getCacheTags')->willReturn($cacheTags);
    $user->method('getCacheContexts')->willReturn([]);
    $user->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);

    return $user;
  }

  /**
   * @covers ::userIsAny
   */
  public function testUserIsAnyShortCircuitsOnFirstAllowAndNeverConsultsLaterSegments(): void {
    $user = $this->mockUser();
    $context = new FixtureSegmentContext();

    $resolver = new FixtureSegmentResolver([
      'InSegment' => AccessResult::allowed(),
      'NotInSegment' => AccessResult::neutral(),
    ]);
    $thing = new FixtureUserSegmentAwareThing($resolver);

    $result = $thing->publicUserIsAny($user, $context, FixtureSegment::InSegment, FixtureSegment::NotInSegment);

    $this->assertTrue($result->isAllowed());
    $this->assertSame([FixtureSegment::InSegment], $resolver->resolvedSegments);
  }

  /**
   * @covers ::userIsAny
   */
  public function testUserIsAnyReturnsNeutralNotForbiddenWhenNoSegmentMatchesAndCarriesConsultedCacheability(): void {
    $user = $this->mockUser(['user:7']);
    $context = new FixtureSegmentContext();

    $resolver = new FixtureSegmentResolver([
      'NotInSegment' => AccessResult::neutral()->addCacheableDependency($user),
    ]);
    $thing = new FixtureUserSegmentAwareThing($resolver);

    $result = $thing->publicUserIsAny($user, $context, FixtureSegment::NotInSegment);

    $this->assertFalse($result->isForbidden());
    $this->assertFalse($result->isAllowed());
    $this->assertContains('user:7', $result->getCacheTags());
    $this->assertSame([FixtureSegment::NotInSegment], $resolver->resolvedSegments);
  }

  /**
   * @covers ::userIsNone
   */
  public function testUserIsNoneForbidsWhenTheVetoFires(): void {
    $user = $this->mockUser();
    $context = new FixtureSegmentContext();

    $resolver = new FixtureSegmentResolver([
      'InSegment' => AccessResult::allowed(),
    ]);
    $thing = new FixtureUserSegmentAwareThing($resolver);

    $result = $thing->publicUserIsNone($user, $context, FixtureSegment::InSegment);

    $this->assertTrue($result->isForbidden());
  }

  /**
   * @covers ::userIsNone
   */
  public function testUserIsNoneAllowsRatherThanNeutralWhenTheVetoDoesNotFireSoAndIfPassesThrough(): void {
    $user = $this->mockUser();
    $context = new FixtureSegmentContext();

    $resolver = new FixtureSegmentResolver([
      'NotInSegment' => AccessResult::neutral(),
    ]);
    $thing = new FixtureUserSegmentAwareThing($resolver);

    $result = $thing->publicUserIsNone($user, $context, FixtureSegment::NotInSegment);
    $this->assertTrue($result->isAllowed());

    // neutral()->andIf($x) would silently discard $x, yielding neutral;
    // allowed()->andIf($x) must pass $x's answer through unchanged.
    $this->assertTrue($result->andIf(AccessResult::forbidden())->isForbidden());
    $this->assertTrue($result->andIf(AccessResult::allowed())->isAllowed());
  }

}

/**
 * A throwaway segment enum, declared for this test only since consumers
 * define their own concrete UserSegmentInterface implementations.
 */
enum FixtureSegment implements UserSegmentInterface {

  case InSegment;

  case NotInSegment;

}

/**
 * A throwaway context, declared for this test only.
 */
final class FixtureSegmentContext implements AbilityContextInterface {}

/**
 * A throwaway resolver double, declared for this test only.
 *
 * Records every segment it is asked to resolve, so tests can assert
 * userIsAny() short-circuits and never consults segments after the first
 * allow.
 */
final class FixtureSegmentResolver implements UserSegmentResolverInterface {

  /**
   * @var \Drupal\user_ability\UserSegmentInterface[]
   */
  public array $resolvedSegments = [];

  /**
   * @param array<string, \Drupal\Core\Access\AccessResult> $answers
   *   Keyed by FixtureSegment case name.
   */
  public function __construct(private readonly array $answers = []) {}

  public function resolve(UserInterface $user, UserSegmentInterface $segment, AbilityContextInterface $context): AccessResult {
    $this->resolvedSegments[] = $segment;

    return $this->answers[$segment->name] ?? AccessResult::neutral();
  }

}

/**
 * A throwaway class exercising the trait, declared for this test only.
 *
 * Exposes public wrappers around the protected userIsAny()/userIsNone() so
 * the test can call them directly.
 */
final class FixtureUserSegmentAwareThing {

  use UserSegmentAwareTrait;

  public function __construct(private readonly UserSegmentResolverInterface $resolver) {}

  public function publicUserIsAny(UserInterface $user, AbilityContextInterface $context, UserSegmentInterface ...$segments): AccessResult {
    return $this->userIsAny($user, $context, ...$segments);
  }

  public function publicUserIsNone(UserInterface $user, AbilityContextInterface $context, UserSegmentInterface ...$segments): AccessResult {
    return $this->userIsNone($user, $context, ...$segments);
  }

  protected function getSegmentResolver(): UserSegmentResolverInterface {
    return $this->resolver;
  }

}
