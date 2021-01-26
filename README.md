# Documentation Laravel-OmogenTalk v2
<em> En cours de développement </em>

Wrapper Php sous Laravel 8


- **[Overview du wrapper ](#overview)**
  

- **[Configuration](#configuration)**
    - **[Installation](#installation)**
    - **[Environnement](#Environnement)**
    - **[Lancement du serveur php](#startserver)**
  

- **[Routes](#routes)**
  

- **[Requests](#requests)**
    - **[Validation](#Validation)**
        - **[Liste des méthodes](#requestmethods)**
    

- **[Controllers](#controllers)**
  

- **[Models](#models)**
    - **[Conversion des attributs Omogen](#convertedattributes)**
    - **[Model resource](#modelresource)**
    - **[Liste des méthodes](#modelmethods)**
        - **[Create](#modelcreate)**
        - **[Save (update)](#modelupdate)**
        - **[UploadDocument](#modelupload)**


- **[Builder Omogen](#omogenbuilder)**
    - **[Méthodes disponibles](#omogenbuildermethods)**
        - **[Where](#omogenbuildermethodwhere)**
        - **[Get](#omogenbuildermethodget)**
        - **[GetResultsRaw](#omogenbuildermethodgetraw)**
        - **[First](#omogenbuildermethodfirst)**
        - **[Count](#omogenbuildermethodcount)**
        - **[Find](#omogenbuildermethodfind)**
  

- **[Resource](#resource)**


- **[Helpers](#helpers)**
    - **[JsonResponse](#helperjsonresponse)**
    


# <a name="overview" ></a> Overview
Le but de ce wrapper et de pouvoir communiquer plus simplement avec Omogen avec une syntaxe relativement proche
de Laravel et ses standards de code.

Le fonctionnement de base des **[Controllers](#controllers)**, des **[Models](#models)** ainsi que celui 
des **[Resources](#resources)** ont été réecris. Ils ne sont donc plus complément avec la logique de Laravel qui est 
prévue pour, avant tout, fonctionner avec un système de gestion de base données orienté SQL. 
Cependant, les fonctionnalités sont toujours présentes et peuvent donc être utilisés indépendamment de la logique Omogen
si un jour le besoin se présente. 

Le fonctionnement de la **[Validation](#Validation)**, du **[Router](#routes)**, des Middlewares et des Providers
restent inchangés. 

# <a name="configuration" ></a> Configuration

L'application restant avant tout une application php Laravel, il faut configurer l'environnement. 

De base, ce wrapper ne requiers aucune configuration spécifique supplémentaire pour fonctionner.

## <a name="installation" ></a> Installation
Cloner le projet
```
git clone http://git.support.asap.net/omogen/laravel-omogentalk-v2.git
```

Conseil pour plus de clarté: <em> Renommez ce dossier `api`</em>
```
mv laravel-omogentalk-v2 api
```

## <a name="envionnement" ></a> Environnement
Placer vous dans le dossier récemment cloné
```
cd api/
```
Copier le contenu du fichier `.env.example` dans un fichier `.env` à la racine du dossier `api`
```
cp .env.example .env
```
Générer une clé
```
php artisan key:generate
```
Renseigner les valeurs suivantes en fonction de votre machine Omogen:
```
APP_NAME=

OMOGEN_LINK=
OMOGEN_DOMAIN=
OMOGEN_NAME=
OMOGEN_ADMIN_LOGIN=
OMOGEN_ADMIN_PASSWORD=
```

## <a name="startserver" ></a> Lancement du serveur
Vous soit utiliser le serveur artisan qui se placera sur port 8000
```
php artisan serve
```
Soit choisir le port en lancant le serveur via php-cli
```
php -S localhost:8000 -t public/
```


# <a name="routes" ></a> Routes
Le fonctionnement des routes ne diffère pas de celui mis en place par Laravel 
**[(Documentation Laravel)](https://laravel.com/docs/8.x/routing)**

Déclarez vos endpoints dans le fichier `api.php` se trouvant dans le dossier `routes`

Pour les routes nécessitant d'être authentifié, vous pouvez créer un groupe de routes de cette manière:
```
Route::middleware('auth')->group(function() {
    Route::post("example-url", "Controller@method")->name('controller.method');
});
```
Vous pouvez avoir plus d'informations sur le middleware d'authentification **[ici](#authenticate)**



# <a name="requests" ></a> Requests
Les requests sont hérités de Laravel. Elles peuvent être invoquées par l'injection de dépendances.
```
# App\Controllers\ExampleController
public function exampleMethod(FormRequest request)
{
    //...
}
```

## <a name="validation" ></a> Validation
La logique des validations ne diffère pas de celle mis en place par Laravel. 

Vous pouvez créer une classe request pour la validation avec la commande artisan:
```
php artisan make:request PathToFile/ExampleRequest
#Créer le fichier app/Http/Requests/PathToFile/ExampleRequest
```

Après cette commande, vous obtenez une classe de validation qui hérite de `Illuminate\Foundation\Http\FormRequest`. 
Vous devez la remplacer par la classe `OmogenTalk\Requests\FormRequest` qui elle-même hérite de `Illuminate\Foundation\Http\FormRequest`
mais proposera des méthodes supplémentaires nécessaires au fonctionnement du wrapper. 

La classe est crée avec deux méthodes: `authorize` et `rules`. La méthode `authorize` permets de déclarer une couche
supplémentaire d'accès à la resource. Elle retourne `true` par défaut et elle est supporté par la classe parent `OmogenTalk\Requests\FormRequest`
. Si vous n'avez aucune couche supplémentaire à instaurer, vous pouvez retirer cette méthode du validateur. Déclarez ensuite 
vos règles de validation dans le tableau de la méthode `rules` comme n'importe quel validateur Laravel.

### <a name="requestmethods" ></a> Méthodes de validation disponibles
En plus des méthodes de base mises en place par Laravel via ses requests, vous pouvez appeler ces méthodes:

```
#Récupère un tableau attributs validés et permets d'en ajouter d'autres à ce même tableau. 

public function getValidatedAttributes(array $optionalAttributes = []): array
{
    return array_merge($this->validated(), $optionalAttributes);
}
```


# <a name="Controllers" ></a> Controllers
La logique des controllers reste la même que celle mis en place par Laravel. 

Vous pouvez créer de nouveau controller via cette commande artisan:
```
php artisan make:controller PathToFile/ExampleController
#Créer le fichier app/Http/Controllers/PathToFile/ExampleController
```

Après la création du fichier, il faudra cependant changer l'héritage de la classe par celle ci: `OmogenTalk\Controllers\Controller`



# <a name="models" ></a> Models
La logique des models sous Laravel et très orientée vers un fonctionnement SQL. Il a donc été nécessaire de récrire cette
logique afin de la rendre fonctionnelle avec le système Omogen. 

Pour palier à ce problème, il est mis à disposition deux classes permettant d'échanger avec le système Omogen: `Omogen` et 
`OmogenBuilder`. La seconde `OmogenBuilder`, est appelée sur un model et permets de pouvoir monter un Builder qui a pour but
d'apporter toutes les informations nécessaires à la requête qui sera exécutée par la première classe `Omogen`. La classe `Omogen`
contient toutes les méthodes "finales" permettant les requêtes et le traitement des réponses venant du système Omogen. 

Si vous souhaitez donc utiliser le système Omogen pour votre model, il est donc indispensable que vous héritiez de la classe
`OmogenTalk\Model\Model` qui vous permettra d'accéder à toutes les méthodes disponibles pour échanger avec le système Omogen.

## Fonctionnement des models Omogen
La classe `OmogenTalk\Model\Model` est une classe abstraite, vous devrez donc dans un premier temps renseigner les 
méthodes requises par le model parent. 

Suite à cela, la configuration du model nécessite plusieurs attributs à renseigner. Voici un example concret:
```
class User extends OmogenTalk\Model\Model
{
    /** @var string Classe de l'objet omogen */
    protected $omogenClass = '.user';

    /** @var string Nom de la classe */
    protected $omogenClassName = 'utilisateur';

    /** @var string Clé primaire */
    public $primaryKey = 'id';

    /** @var string[] Liste des attributs Omogen à convertir pour une requête put */
    protected $omogenConvertedAttributes = [
        OmogenBuilder::METHOD_GET => [
            'prenom' => 'first_name',
            'nom' => 'last_name',
            'telephone_fixe' => 'phone_number',
            'e_mail' => 'email',
            'administrateur' => 'administrator',
            'created' => 'created_at',
            'modified' => 'updated_at',
        ],
        OmogenBuilder::METHOD_PUT => [
            'rcs' => 'raison sociale',
            'address' => 'adresse',
            'address_complement' => 'complement adresse',
            'cp' => 'code postal',
            'town' => 'ville',
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'service' => 'service',
            'phone_number' => 'téléphone fixe',
            'email' => 'e-mail',
            'password' => 'mot de passe'
        ],
    ];
    
    /** @var string Resource du model */
    public $resource = UserResource::class;
}
```
Les attributs de classe `protected $omogenClass`, `protected $omogenClassName` ainsi que `public $primaryKey`
sont nécessaires pour la création du builder qui va permettre de pouvoir créer l'url de requête. 

### <a name="convertedattributes" ></a> Conversion des attributs Omogen
L'attribut `protected $omogenConvertedAttributes` est un tableau permettant de pouvoir convertir les attributs afin
d'être sur la bonne syntaxe avec le système Omogen. Sont fonctionnement est simple:
- Il faut renseigner la méthode de requête, exemple : `OmogenBuilder::METHOD_GET` (utilisez les constantes mises en place
  sur la librairie OmogenBuilder)
- Renseignez les champs obtenu par le système via une requête GET qui imposer la syntaxe de convertion requise pour votre 
système, example: `"telephone_fixe" => "phone_number"`. Lorsque vous allez recevoir un attribut `telephone_fixe` venant de Omogen, il sera
  traduit par `phone_number` dans les attributs de votre model.
  
Pour le fonctionnement des requêtes `PUT`, il s'agit du même, exemple : `"phone_number" => "téléphone fixe"`. Lors de la
création de l'url de requête vers Omogen, votre attribut de classe `phone_number` sera traduit en `téléphone fixe`. 

ATTENTION: Si les attributs ne sont pas déclarés, ils ne seront pas pris en compte lors des requêtes. Par exemple, si vous 
souhaitez créer un utilisateur avec un attribut `phone_number` mais qu'il n'est pas présent dans le tableau de conversion,
il sera ignoré lors de la création du builder ! Et donc non présent dans le système Omogen.


### <a name="modelresource" ></a> Model resource
L'attribut `public $resource` défini la classe `Resource` lié au model. Elle est optionnelle, renseignez la uniquement si
vous souhaitez utiliser le système des resources. 

## <a name="modelmethods" ></a> Liste des méthodes du model
### <a name="modelcreate" ></a> Create
Vous pouvez créer une entité via la méthode statique `create(FormRequest)` en passant la request en paramètre :
```
#app/Http/Controllers/UserController

public function createUser(CreateRequest $request)
{
    $user = User::create($request);
    return (new UserResource($user))->toJsonResponse();
}
```
La request va permettre au model de récupérer les attributs validés ainsi que le token d'authentification qui sera passé
dans le header. 

### <a name="modelsave" ></a> Save
La méthode `save()` permet de pouvoir mettre à jour une entité. Il faut l'appeller sur le model courant après avoir effectué
les changements d'attributs : 
```
#app/Http/Controllers/UserController

public function editUserFirstname(EditRequest $request)
{
    $user = User::getQueryBuilder()->find($request->input('user_id'));
    $user->first_name = 'Florian';
    $user->save();
}
```

### <a name="modelupload" ></a> UploadDocument
La méthode `uploadDocument(FormRequest)` permet d'associer plusieurs documents à une entité sur le système Omogen:
```
#app/Http/Controllers/UserController

public function uploadUserDocument(UploadRequest $request)
{
    $user = User::getQueryBuilder()->find($request->input('user_id'));
    $user->uploadDocument($request);
}
```
La méthode prends en paramètre la request qui utilisera ensuite la méthode `allFiles(): array` qui regroupera tous les
documents à envoyer sur le système Omogen. Il est donc nécessaire que les attributs des documents soient prévu à l'avance:
```
$documents = $request->allFiles();
$documents === [
    'kbis' => UploadedFile,
    'rib' => UploadedFile
];
```


# <a name="omogenbuilder" ></a> Builder Omogen
Afin de pouvoir effectuer des requêtes sur le système Omogen, un builder à été mis en place. Son but est de préparer tout
une liste d'éléments afin de permettre à la librairie Omogen d'effectuer une requête vers les systèmes d'Omogen.

Pour l'invoquer, il faut l'appeler de manière statique sur le model dont vous souhaitez agir via la méthode
`getQueryBuilder(array): OmogenBuilder`:
```
# app\Http\Controllers\UserController

public function getUser(FormRequest $request)
{
    /** @var OmogenTalk\Lib\OmogenBuilder */
    $omogenBuilder = User::getQueryBuilder();
}
```
La méthode `getQuerybuilder(array)` prends en paramètre un tableau dans lequel vous pouvez passer plusieurs attributs 
en fonction de vos besoin pour votre requête :
```
data (bool) | Permet de récupérer les attributs de l'objet
User::getQueryBuilder(['data' => true]);

depth (int) | Permet de récupérer une profondeur un objet
User::getQueryBuilder(['depth' => 2]);

token (string) | Permet d'injecter le token nécessaire dans la requête
User::getQueryBuilder(['token' => $token]);
```

## <a name="omogenbuildermethods" ></a> Builder Omogen
Différentes méthodes de builder sont disponibles. 

### <a name="omogenbuildermethodwhere" ></a> Where
La méthode `where()` simule une recherche avancée du système Omogen. Elle prends en paramètre:
- L'attribut Omogen
- L'opérateur
- La valeur
```
$builder = User::gerQuerybuilder(['data' => true])->where('prenom', 'est', 'Florian');
// Le query du builder sera donc: query=utilisateurs dont le prenom est Florian
```
Elle doit toujours être suivi d'une méthode "getter" comme `get()` ou `find()`
```
$users = User::gerQuerybuilder(['data' => true])->where('age', 'est', 29)->get();
```

### <a name="omogenbuildermethodget" ></a> Get
La méthode `get()` execute le builder précedement crée. La méthode `get()` est un getter, elle doit être
appelée à la conclusion de clauses comme `where()`.

Elle ne prends aucun paramètre et retourne une liste de models :
```
$users = User::gerQuerybuilder(['data' => true])->where('age', 'est', 29)->get();
```

### <a name="omogenbuildermethodgetraw" ></a> GetResultsRaw
La méthode `getResultsRaw()` execute le builder précedement crée tout comme la méthode `get()` sauf qu'elle rentourne le résultat brut
de la réponse du système Omogen. La méthode `getResultsRaw()` est un getter, elle doit être
appelée à la conclusion de clauses comme `where()`.

Elle ne prends aucun paramètre et retourne une liste :
```
$users = User::gerQuerybuilder(['data' => true])->where('age', 'est', 29)->getResultsRaw();
```

### <a name="omogenbuildermethodfirst" ></a> First
La méthode `first()` retourne le premier élément trouvé d'une liste de résultats. La méthode `first()` est un getter, elle doit être
appelée à la conclusion de clauses comme `where()`.

Elle ne prends aucun paramètre et retourne un model :
```
$user = User::gerQuerybuilder(['data' => true])->where('age', 'est', 29)->first();
```

### <a name="omogenbuildermethodcount" ></a> Count
La méthode `count()` retourne le nombre d'éléments trouvés. La méthode `count()` est un getter, elle doit être
appelée à la conclusion de clauses comme `where()`.

Elle ne prends aucun paramètre et retourne un int :
```
$user = User::gerQuerybuilder(['data' => true])->where('age', 'est', 29)->count();
```

### <a name="omogenbuildermethodfind" ></a> Find
La méthode `find(string)` permet de rechercher une entité via son identifiant.

Elle prends en paramètre une chaîne de caractères et retourne un model ou `null` si aucune correspondance n'a été trouvée :
```
$user = User::gerQuerybuilder(['data' => true])->find($userId);
```
Pour fonctionner, vous devez avoir renseigné l'attribut de classe `primaryKey`:
```
# app\Models\user

public $primaryKey = 'id';
```


# <a name="resource" ></a> Resources
Les resources permettent de rendre une réponse dans un format standard API REST avec le choix des attributs. 

Vous pouvez créer une resource en effectuant la commande artisan:
```
php artisan make:resource PathToFile/ExampleResource
```
Il vous faudra ensuite hériter de la classe `OmogenTalk\Resources\Resource` afin de profiter des méthodes disponibles et
de déclarer la classe dans le model avant utilisation :
```
# app\Models\User
public $resource = app\Resources\UserResource::class;
```

Les resources fonctionnent de manière simple. Il suffit créer une nouvelle instance de la classe et de passer en paramètre
le model:
```
# app\Http\Controllers\UserController

public function getUser(FormRequest $request)
{
    $user = User::getQueryBuilder()->find($request->input('user_id'));
    return (new UserResource($user))->toJsonResponse();
}
```
La méthode rendra une réponse sous ce format:
```
{
    data: {
        type: "Utilisateur",
        attributes: {
            first_name: "Florian",
            last_name: "Javanet",
            phone_number: "+33621548754"
        }
    }
}
```
La resource place les attributs du model dans un objet `attributes`. Par défaut, la resource place tous les attributs 
du model dans l'objet, mais vous pouvez choisir de sélectionner les attributs si vous le souhaitez en surchargeant la méthode
`toArray(): array`:
```
# app\Resources\UserResources

public function toArray(): array
{
    return [
        "first_name" => $this->resource->first_name,
        "last_name" => $this->resource->last_name,
    ];
}
```

Et la réponse sera: 
```
{
    data: {
        type: "Utilisateur",
        attributes: {
            first_name: "Florian",
            last_name: "Javanet"
        }
    }
}
```
De cette même manière, vous pouvez ajouter des attributs manuellement :
```
# app\Resources\UserResources

public function toArray(): array
{
    $attributes = parent::toArray();
    return array_merge($attributes, [
        "specific_attribute" => "specific_value"
    ]);
}
```

Et la réponse sera: 
```
{
    data: {
        type: "Utilisateur",
        attributes: {
            first_name: "Florian",
            last_name: "Javanet",
            phone_number: "+33621548754",
            specific_attribute: "specific_value"
        }
    }
}
```
Vous pouvez accéder aux méthodes et aux attributs de votre model via l'attribut de classe `resource`:
```
# app\Resources\UserResources

public function toArray(): array
{
    return [
        "first_name" => $this->resource->first_name,
        "last_name" => $this->resource->last_name,
        "id" => $this->resource->getId(),
    ];
}
```
Et la réponse sera: 
```
{
    data: {
        type: "Utilisateur",
        attributes: {
            first_name: "Florian",
            last_name: "Javanet",
            phone_number: "+33621548754",
            id: "ZZUHABSOUYVAO89BBAI"
        }
    }
}
```


# <a name="helpers" ></a> Helpers
Différents helpers sont disponibles afin de fonctionner efficacement avec les réponses du système Omogen:

### <a name="helperjsonresponse" ></a> JsonResponse
Vous pouvez à tout moment renvoyer une réponse au format Json via le helper `jsonResponse(array)`:
```
# app\Http\Controllers\AuthController

public function checkIfUserExists(FormRequest $request): Response
{
    $exists = User::getQueryBuilder()->find($request->input('user_id'));
    
    $response = ['status' => 200, 'message' => 'OK'];
    
    if (!$exists) {
        $response = [
            'status' => 404,
            'message' => 'Aucun utilsateur trouvé avec cet id'
        ];
    }
    
    return jsonResponse($response);
}
```



