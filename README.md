# User Ability

The **User Ability** module provides a clean, declarative way to define and evaluate user business capabilities in Drupal. It separates business capability names from underlying access mechanisms (roles, permissions, entity ownership, group memberships, etc.) and evaluates them with full cache metadata support.

## Core Concepts

- **Ability (`AbilityInterface`)**: An enum representing a business capability. By convention, enum cases are named as verbs or verb phrases describing the action (e.g. `SiteAbility::PublishArticle`, `SiteAbility::DeleteComment`).
- **Context (`AbilityContextInterface`)**: A value object carrying entities or domain data required to evaluate an ability beyond the user account itself.
- **Check (`AbilityCheckInterface`)**: A dedicated service that resolves a single ability into an `AccessResultInterface`, managing cacheability metadata.
- **Checker (`AbilityChecker`)**: The centralized dispatcher service (`user_ability.checker`) that resolves context and routes check requests to the registered check implementation.

---

## Implementation Example

### 1. Define the Ability Enum

Implement `\Drupal\user_ability\AbilityInterface`. By convention, **enum cases should always be named as verbs or verb phrases** (e.g. `PublishArticle`, `EditArticle`, `DeleteComment`) representing the action being performed rather than a role or permission. Because pure enums cannot be natively JSON-serialized by PHP's `json_encode()`, `AbilityInterface` requires implementing `\JsonSerializable`.

```php
namespace Drupal\my_module\Enum;

use Drupal\user_ability\AbilityInterface;

enum SiteAbility implements AbilityInterface {
  case PublishArticle;
  case EditArticle;
  case DeleteComment;

  public function jsonSerialize(): string {
    return $this->name;
  }
}
```

### 2. Define the Ability Context

Implement `\Drupal\user_ability\AbilityContextInterface` to pass context data (such as nodes, groups, or status codes) to your ability checks.

```php
namespace Drupal\my_module\Context;

use Drupal\node\NodeInterface;
use Drupal\user_ability\AbilityContextInterface;

final class ArticleContext implements AbilityContextInterface {

  public function __construct(
    public readonly ?NodeInterface $article = NULL,
  ) {}

}
```

### 3. Implement the Ability Check

Implement `\Drupal\user_ability\AbilityCheckInterface`. The check evaluates access and returns a cacheable `AccessResultInterface`. It must also provide a fallback default context via `getDefaultContext()`.

```php
namespace Drupal\my_module\AbilityCheck;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\my_module\Context\ArticleContext;
use Drupal\user\UserInterface;
use Drupal\user_ability\AbilityCheckInterface;
use Drupal\user_ability\AbilityContextInterface;

final class PublishArticleCheck implements AbilityCheckInterface {

  /**
   * {@inheritdoc}
   */
  public function abilityAccess(UserInterface $user, AbilityContextInterface $context): AccessResultInterface {
    assert($context instanceof ArticleContext);

    $result = AccessResult::allowedIf($user->hasPermission('administer nodes') || $user->hasPermission('publish article content'))
      ->addCacheableDependency($user)
      ->cachePerPermissions();

    if ($context->article) {
      $result = $result->addCacheableDependency($context->article);
      // Example condition: authors can publish their own unpublished drafts.
      $is_author = (int) $context->article->getOwnerId() === (int) $user->id();
      $result = $result->orIf(
        AccessResult::allowedIf($is_author && $user->hasPermission('publish own article content'))
          ->addCacheableDependency($user)
          ->addCacheableDependency($context->article)
          ->cachePerPermissions()
      );
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultContext(): AbilityContextInterface {
    return new ArticleContext();
  }

}
```

### 4. Register the Check Service

Register your check in `my_module.services.yml` with the `ability_check` tag, referencing the fully-qualified case reference in the `ability` attribute:

```yaml
services:
  my_module.ability_check.publish_article:
    class: Drupal\my_module\AbilityCheck\PublishArticleCheck
    tags:
      - name: ability_check
        ability: 'Drupal\my_module\Enum\SiteAbility::PublishArticle'
```

### 5. Check Abilities

Inject or call the `user_ability.checker` service (`\Drupal\user_ability\AbilityChecker`):

```php
use Drupal\my_module\Context\ArticleContext;
use Drupal\my_module\Enum\SiteAbility;

/** @var \Drupal\user_ability\AbilityChecker $ability_checker */
$ability_checker = \Drupal::service('user_ability.checker');
$account = \Drupal::currentUser();

// Checking without explicit context (uses getDefaultContext()):
$can_publish = $ability_checker->check(
  SiteAbility::PublishArticle,
  $account
)->isAllowed();

// Checking with explicit context:
$context = new ArticleContext($node);
$can_publish_article = $ability_checker->check(
  SiteAbility::PublishArticle,
  $account,
  $context
)->isAllowed();
```
