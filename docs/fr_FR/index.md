| :warning: WARNING          |
|:---------------------------|
| Currently in beta ...      |
| (see relative branch)      |

# Description :

Plugin permettant de communiquer avec les pompes à chaleur (PAC) de la marque Mitsubish, qui sont reliés à leurs serveurs MELCloud.

# Configuration :
A l'installation du plugin, les paramètres de connexion sont à remplir dans la configuration du plugin :
![configuration](../Configuration.png?raw=true)

Voici l'ordre des éléments à renseigner :
1. L'adresse email et le mot de passe utlisé pour se connecter à MELCloud (l'application)
2. Sauvegarder via le bouton présent sur la ligne `Configuration`
3. Cliquer sur le bouton `Récupérer le Token MELCloud`
4. Cliquer sur le bouton `Récupérer les équipements MELCloud`

La version de l'application, disponible dans la partie avancée correspond à la version de l'application de MELCloud. Actuellement, même avec une version pas à la dernière version, cela fonctionne. Toutefois, il est possible que cette version vienne à devoir être mise à jour dans le cas d'une grande refonte de la part de Mitsubishi.
Donc, en l'état, pas besoin de changer cette valeur.

# Création des équipements :
Dans la configuration, après avoir cliqué sur `Récupérer les équipements MELCloud`, toutes les PAC associés à ce compte sont ajoutées à Jeedom.
Si vous n'avez pas lancé cette action, ou s'il y a des changements sur les équipements de votre logement, le bouton `Synchroniser` de la page d'accueil du plugin permet de relancer le processus. Aussi, un cron réalise cette action de façon régulière.

Il ne vous reste donc qu'à activer et à associer chaque élément au bon objet de votre Jeedom afin de le retrouver (ou non) sur le dashboard.

# Widget :
Une fois créé, chaque équipement sera visible (si demandé) sur le dashboard avec le design suivant :
![widget](../Widget.png?raw=true)
Design que j'ai voulu le plus proche possible de l'appli MELCloud, afin de garder les habitudes des personnes ayant déjà celle-ci.

Lors de l'envoi d'une nouvelle valeur (de mode, de température, ...) depuis Jeedom, des petits points apparaissent à la suite du mot `rafraichir`, indiquant la communication entre Jeedom et votre PAC via les serveurs MELCloud. Ceux-ci disparaissent quand l'échange est terminé, et l'ensemble du widget est mis à jour avec les dernières valeurs d'état de la PAC.

# Contribuer :
Vous déceler un bug, vous voulez proposer une amélioration, n'hésitez pas à le dire sur le forum (avec le tag dédié). Vous pouvez aussi directement faire un PR sur [le dépôt de ce plugin](https://github.com/DuchkPy/mitsubishimelcloud).

Vous disposez d'une PAC air/eau ? Merci de me contacter (via le forum, de préférence), que je puisse mettre à jour le plugin pour prendre en compte cette autre possibilité.

# Remerciement :
Au travail initié par MGeek pour la retro-ingénierie sur l'app MELCloud [http://mgeek.fr/blog/un-peu-de-reverse-engineering-sur-melcloud](http://mgeek.fr/blog/un-peu-de-reverse-engineering-sur-melcloud) ([archive](https://web.archive.org/web/20220120005605/http://mgeek.fr/blog/un-peu-de-reverse-engineering-sur-melcloud)).
Puis au premier plugin créé par [Floman321](https://github.com/floman321/melcloud), malheureusement plus à jour, que j'ai forké pour continuer à supporter les PAC Mitsubishi.

# Licence :
Comme voulu par MGeek (voir pied de page de son site web), ce plugin est sous licence [Creative Commons Attribution - Pas d’Utilisation Commerciale - Partage dans les Mêmes Conditions 4.0 International (CC BY-NC-SA 4.0)](https://creativecommons.org/licenses/by-nc-sa/4.0/deed.fr), donc gratuit.

# Changelog :
[Changelog](./changelog.md)