****Transaction Manager****

***Basic usage***
```php
use Anboo\TransactionManager\TransactionManager;
use Anboo\TransactionManager\TransactionInterface;

$transactionManager = new TransactionManager();
$transactionManager->addTransaction(fn() => file_get_contents('http://'));

$transactionManager->addTransaction(new class implements TransactionInterface {
    public function up()
    {
        $this->remoteServiceClient->createEntity(...);
    }

    public function down()
    {
        $this->remoteServiceClient->removeEntity(...);
    }
});

$transactionManager->addTransaction(fn() => /* Database Insert */)

try {
    $transactionManager->run();
} catch (Throwable $e) {
    echo 'All completed transaction has been rollback';
}
```

***Merge transactions***
```php
use Anboo\TransactionManager\TransactionManager;

$transactionManagerA = new TransactionManager();
$transactionManagerA->addTransaction(...);

$transactionManagerB = new TransactionManager();
$transactionManagerB->addTransaction(...);
$transactionManagerB->addTransaction(...);
$transactionManagerB->addTransaction(...);

$transactionManagerB->merge($transactionManagerA);
$transactionManagerB->run();
```

***Ignore specific exception for transaction***
```php
use Anboo\TransactionManager\TransactionManager;

$transactionManagerA = new TransactionManager();
$transactionManagerA->addTransaction(...);
$transactionManagerA->addIgnoreException(UserAlreadyExistsException::class);

$transactionManagerA->run();
```
