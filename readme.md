# Laravel App Events for Google Cloud PubSub

## Installation

Install through Composer with `composer require decahedron/laravel-app-events`

## Usage

The App Event as implemented in this package is just a regular Laravel job class
that gets dispatched as usual with the `dispatch` method.

### Dispatching ("broadcasting") events

When you dispatch an app event, you must provide an event name, and a payload
in the form of a protobuf message.

```php
dispatch(new AppEvent('user.created', new User(['name' => $user->name])));
```

In order for this to get properly encoded, you also need to provide a mapping
from a name to the class implementation. This allows the package to encode the
message in a way that is not bound exclusively to PHP, and thus allows decoding
the message into a protobuf structure in any language that protobuf supports.

#### Configuration for broadcasting

```php
return [
    'enabled' => true,
    'project_id' => 'your-google-project',
    'topic' => 'app-events',
    'subscription' => 'login-handler'

    'mappings' => [
        'User' => App\Proto\User::class,
    ],
];
```

### Handling events

Events can be handled by any application that is able to communicate with
Google Cloud PubSub, and is written in a language that can use protobuf.

To handle events with this package, your handling application (which may be
the same as the dispatching one) must contain the following configuration.

```php
return [
    'enabled' => true,
    'project_id' => 'your-google-project',
    'topic' => 'app-events',
    'subscription' => 'user-registrator',

    'mappings' => [
        'User' => App\Proto\User::class,
    ],
    
    'handlers' => [
        'user.created' => App\Auth\RegisterUser::class,
    ],
];
```

The handler specified here must be a class with a `handle` method,
which accepts the protobuf message as an argument. This class gets resolved
through the Laravel container, so you may use constructor injection:

```php
class RegisterUser
{
    protected $registrator;

    public function __construct(Registrator $registrator)
    {
        $this->registrator = $registrator;
    }

    public function handle(User $user)
    {
        $this->registrator->register(
            $user->getName(),
            $user->getEmail(),
        );
    }
}
```

Note that the `mappings` are still required here, in order to convert the
data back into the correct protobuf message. Therefore, it might be
beneficial to place your base configuration (not including the subscription name
and handlers) in a shared location so it can be updated in all places at once.