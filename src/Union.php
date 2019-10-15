<?php
declare(strict_types=1);

namespace Natepisarski\DiscriminatedUnion;

class Union
{
    public static function Create($name) {
        return Union($name);
    }
}

/**
 * Union function - the heart of the discriminated union system. Using "Union" you can create a "DiscriminatedUnionBuilder".
 *
 * The builder can then be used to define your match cases with which you will be using the Union.
 *
 * Union itself is a function that points to where the real work happens - the UnionBuilder.
 */

function Union(string $name): UnionBuilder
{
    return new UnionBuilder($name);
}

/**
 * Class UnionBuilder
 * @package Natepisarski\DiscriminatedUnion
 *
 * This is where the real magic happens. UnionBuilder has two methods: of, and render.
 *
 *=OF===================================================================================================================
 * of sets up "match arms". In a normal FP language, you'd see a definition like this:
 *
 *      > class User = Admin (name: string) | EndUser (name: string)
 *
 * Under the hood, this will simply create a functional constructor that can create a list of properties.
 *
 * What this means, is that if you feed in the following:
 *
 *      > of('Admin', fn($string) => [$string])
 *
 *                      or
 *
 *      > of('Admin', fn($string) => ['name' => $string])
 *
 * you will eventually get a simple array out, with one member: (the name).
 *
 * Both of the above statements are functionally equivalent because you can't use named arrays in DiscriminatedUnion.
 *
 *=RENDER===============================================================================================================
 * Render simply takes the "of" arms you've given it and renders a function that can create either of the arms. You should
 * use a capital letter to denote that this will act like a class constructor, when in actuality it's simply an anonymous
 * function that has been returned
 * =====================================================================================================================
 */
class UnionBuilder
{
    // Some things should be turned on when PHP 7.4 hits PsySH

    protected /*string*/
        $name;

    protected /* arrayOf(String|Function) */
        $arms;

    /**
     * Construct the builder with no match arms
     * UnionBuilder constructor.
     * @param $discriminatedUnionName
     */
    public function __construct($discriminatedUnionName)
    {
        $this->name = $discriminatedUnionName;
        $arms = [];
    }

    /**
     * Add a name and a constructor to the arms array
     * @param $armName
     * @param $constructor
     * @return $this
     */
    public function of($armName, $constructor)
    {
        $this->arms[$armName] = $constructor;
        return $this;
    }

    /**
     * Turn the match arms into an anonymous function that can construct multiple different kinds of classes
     */
    public function render()
    {
        $unionName = $this->name;
        $currentArms = $this->arms;
        /*
         * $ClientType = $b->render();
         *
         * $ClientType('ClientSuccess')($s)
         * $result = $ClientType('ClientFailure')($s)
         *
         * [$id] = match($result)
         *  ->on('ClientSuccess', ($r) => [$r->id])
         */

        // Create a bag where we can dynamically add methods
        $CONSTRUCTOR = new class {
            function addMethod($name, $method)
            {
                $this->{$name} = $method;
            }

            public function __call($name, $arguments)
            {
                return call_user_func($this->{$name}, $arguments);
            }
        };

        // For every arm that's been added...
        foreach (array_keys($currentArms) as $armName) {

            // $UserType = ...->render();

            // Add a new method that acts as an arm constructor. It can be called like this:
            // $admin = $UserType->Admin($id, $name, ...)
            $CONSTRUCTOR->addMethod($armName, function (...$arguments) use ($currentArms, $unionName, $armName) {
                // The $result variable is a simple array of [UnionName, ArmName, $VALUE] where $VALUE is what is
                //  returned from the constructor.
                $currentArm = $currentArms[$armName];

                return [
                    $unionName,
                    $armName,
                    $currentArm(...$arguments)
                ];
            });
        }

        // That's all it took!
        return $CONSTRUCTOR;
    }
}