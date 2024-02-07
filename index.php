<?php
/** Funcions about user handling to make the index file clear for route handler
 * isLoggedIn, isAuth, loginHandler, logoutHandler
 */
require './auth.php';
// registerRoutes, get_string_between
require './router.php';
// slugify
require './slugifier.php';

// what method the page got
$method = $_SERVER["REQUEST_METHOD"];
// what URL-/direction want the request use
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];

// Útvonalak regisztrálása
$routes = [
    // ['METHOD','/direction','handlerFunctionName']

    // route kategorizálása átláthatóság céllal - ez segített megérteni mikor használunk POST ot és mikor GET-et

    // Userhandler
    ['POST', '/logout', 'logoutHandler'],
    ['POST', '/login', 'loginHandler'],

    // Page Handler
    ['GET', '/', 'homeHandler'],
    ['GET', '/admin', 'adminHandler'],
    ['GET', '/admin/etel-tipusok', 'dishTypeHandler'],
    ['GET', '/admin/uj-etel-letrehozasa', 'dishCreateFormHandler'],
    ['GET', '/admin/etel-szerkesztese/{keresoBaratNev}', 'dishEditFormHandler'],

    // Function handler
    ['POST', '/create-dish', 'dishCreateHandler'],
    ['POST', '/create-dish-type', 'dishTypeCreateHandler'],
    ['POST', '/update-dish/{id}', 'dishEditHandler'],
    ['POST', '/delete-dish/{id}', 'dishDeleteHandler'],
];

// Útvonalválasztó inicializálása
$dispatch = registerRoutes($routes);
$matchedRoute = $dispatch($method, $path);
$handlerFunction = $matchedRoute['handler'];
$handlerFunction($matchedRoute['vars']);

/** /admin/uj-etel-letrehozasa
 * create new dish form page 
 * needs the dish types for the actual options
 * redirect to /create-dish with POST when it's success
 */
function dishCreateFormHandler(){
    isAuth();
    $pdo= getConnection();

    $statement = $pdo ->prepare('SELECT * FROM `dishTypes` ORDER BY `dishTypes`.`id` ASC');
    $statement -> execute();
    $dishtypes = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo render('admin-wrapper.phtml',[
        'content' => render('create-dish.phtml',[
            'dishtypes' => $dishtypes
        ])
    ]);
}
/** /create-dish
 * create dish function handler 
 * use the POST from /admin/uj-etel-letrehozasa
 * SQL: INSERT INTO `dishes` (`name`, `slug`, `description`,`price`,`isActive`,`dishTypeId`) 
 * VALUES ('Paradicsom főzelék','paradicsom-fozelek','Paradicsom főzelék lorem','1200','1','2');
 */
function dishCreateHandler(){
    isAuth();

    $name = $_POST['name'] ?? '';
    $slug = slugify($_POST['name']);
    $description = $_POST['description'] ?? '';
    $price = (int)$_POST['price'] ?? 0;
    $dishTypeId =(int) $_POST['dishTypeId'] ?? 0;
    $isActive = (int)isset($_POST['isActive']);

    $pdo = getConnection();

    $statement = $pdo -> prepare('INSERT INTO `dishes` 
    (`name`, `slug`, `description`, `price`, `isActive`, `dishTypeId`) 
        VALUES (?,?,?,?,?,?);
    ');
    $statement->execute([$name, $slug, $description, $price, $isActive, $dishTypeId]);
    
    header('Location: /admin');
}

/** /delete-dish/{id}
 * Function to delete the selected item from id linked to delete button
 * from /admin
 * SQL DELETE FROM `dishes` WHERE id = 1
 */
function dishDeleteHandler($vars){
    isAuth();

    $pdo = getConnection();

    $statement= $pdo->prepare('DELETE FROM `dishes` WHERE id = ?');
    $statement->execute([$vars['id']]);

    header('Location: /admin');
}

/** admin/etel-szerkesztese/{slug}
 * Function to Update the edited data into the same selected dish
 * which send the form with POST and the id
 * from /admin/etel-szerkesztese/{slug}
 * NOT USING $_POST['slug']! -> $slug = slugify($_POST['name']);
 * SQL UPDATE `dishes` SET name='Doe' WHERE id=2
 * data struct: name,slug,description,price,isActive,dishTypeId
 */
function dishEditHandler($vars){
    isAuth();

    $name = $_POST["name"]?? '';
    $slug = slugify($_POST['name'] ?? '');
    $description = $_POST["description"] ?? '';
    $price = (int)$_POST["price"] ?? 0;
    $dishTypeId = (int)$_POST["dishTypeId"] ?? 0;
    $isActive = (int)isset($_POST['isActive']);

    $pdo = getConnection();

    /**
     * Using this :id instend ?, I discovered in the last time,
     * it makes easier to link the POST data with the params 
     * while it's works as same to avoid the SQL injections
     */
    $statement= $pdo->prepare('UPDATE `dishes` SET 
        name=:name, 
        slug=:slug, 
        description=:description, 
        price=:price, 
        dishTypeId=:dishTypeId,
        isActive=:isActive 
            WHERE id = :id
    ');
    $statement->execute([
        ':id'=>$vars['id'],
        ':name' => $name,
        ':slug' => $slug,
        ':description' => $description,
        ':price' => $price,
        ':dishTypeId' => $dishTypeId,
        ':isActive' => $isActive,
    ]);

    
    header('Location: /admin');
};

/** /admin/etel-szerkesztese/{slug}
 * Form page to edit the selected dish from the /admin page and
 * get the SLUG through redirection with "szerkesztes" button.
 * Get the whole data with query - slug and fill the form 
 * value with the data.
 * Need dishTypes for actual type list in edit
 */
function dishEditFormHandler($vars){
    isAuth();    
    $pdo = getConnection();

    $statement= $pdo->prepare('SELECT * FROM `dishes` WHERE slug = ?');
    $statement->execute([$vars['keresoBaratNev']]);
    $dish = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $pdo ->prepare('SELECT * FROM `dishTypes` ORDER BY `dishTypes`.`id` ASC');
    $statement -> execute();
    $dishtypes = $statement->fetchAll(PDO::FETCH_ASSOC);


    echo render("admin-wrapper.phtml", [
        'content' => render("edit-dish.phtml",[
            'editDish' => $dish,
            'dishtypes' => $dishtypes
    ])
    ]);
}

/** /create-dish-type
 * Function for upload to a new dish type to the list
 * get the data throug POST from /admin/etel-tipusok
 * redirect to /admin/etel-tipusok when success
 */
function dishTypeCreateHandler(){
    // INSERT INTO `dishTypes` (`name`, `slug`, `description`) VALUES ('Sajt', '', 'Pont az');
    isAuth();
    
    $name = $_POST['name'] ?? '';
    $slug = slugify($_POST['name'] ?? '');
    $description = $_POST['description'] ?? '';

    $pdo = getConnection();

    $statement = $pdo -> prepare('INSERT INTO `dishTypes` (`name`, `slug`, `description`) 
    VALUES (?,?,?)');
    $statement->execute([$name, $slug, $description]);

    header('Location: /admin/etel-tipusok');
}

/** /admin/etel-tipusok
 * Check the dish types and able to create a new type
 * through form and POST to /create-dish-type
 */
function dishTypeHandler(){
    isAuth();

    $pdo = getConnection();

    $statement = $pdo -> prepare('SELECT * FROM `dishTypes`');
    $statement ->execute();
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo render("admin-wrapper.phtml",[
        'content' => render("dish-type-list.phtml",[
            'dishTypes' => $dishTypes,
        ])
    ]);
}

/** /admin
 *  After login - basic dish list with editing functions 
 */
function adminHandler(){
    if (!isLoggedIn()) {
        echo render("wrapper.phtml",[
            'content' => render("login.phtml")
        ]);
        exit;
    }
    
    $pdo = getConnection();

    $statement = $pdo->prepare('SELECT * FROM `dishes`');
    $statement->execute();
    $dishes = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo render("admin-wrapper.phtml",[
        'content' => render("dish-list.phtml",[
            "dishes" => $dishes,
        ])
    ]);
}

/** Main page without logged in /'
 * first get the type, then for every type we get the dishes
 */
function homeHandler(){
    $pdo = getConnection();

    $statement = $pdo -> prepare('SELECT * FROM `dishTypes`');
    $statement -> execute();
    $dishTypes = $statement -> fetchAll(PDO::FETCH_ASSOC);

    foreach ($dishTypes as $index => $dishType) {
        $statement = $pdo -> prepare('SELECT * FROM `dishes` WHERE dishTypeId = ? AND isActive = 1');
        $statement -> execute([$dishType['id']]);
        $dishes = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $dishTypes[$index]['dishes'] = $dishes;
    }
    
    echo render("wrapper.phtml",[
        'content' => render("public-menu.phtml",[
            'dishTypesWithDishes' => $dishTypes,            
        ])
    ]);
}

//page if it's not on the map
function notFoundHandler(){
    echo 'Oldal nem található';
}

// Collect all data into the buffer, what we will use on the $path - page, before its build up
function render($path, $params = []){
    ob_start();
    require __DIR__ . '/views/' . $path;
    return ob_get_clean();
}

//Basic SQL connection, everytime it need to call, using .env
function getConnection(){
    return new PDO(
        'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
        $_SERVER['DB_USER'],
        $_SERVER['DB_PASSWORD']
    );
}
