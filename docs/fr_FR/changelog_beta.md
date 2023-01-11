# TODO
 - Ajouter les PAC air/eau (si vous en possédez une, merci de me contacter)
 - Mettre à jour la gestion du cron
    - Gestion de la fréquence configurable
 - Ajouter la possibilité de choisir quel élément du widget afficher ou non (parmi `Scénario`, `Mode`, `Vitesse de ventilation`, `Ailettes horizontales`, `Ailettes verticales`, `Température`, `Météo`)

# 11/01/2022 (V0.9)
 - Bug fix, corrige une remonté d'erreur dans les logs http.error

# 20/12/2022 (v0.8)
 - Mise à jour du core du plugin pour être pleinement compatible avec le plugin JeedomConnect.

# 08/12/2022 (v0.7)
 - Ajout de la gestion des scénarios. Juste 4 possible par équipement.

# 02/12/2022 (v0.6)
 - Ajout de la gestion des erreurs de connections aux serveurs MELCLoud. Une commande `WarningText` a été ajoutée pour remonter cette information. Elle vaut `0` dans le cas où il n'y a pas d'erreur. En cas d'erreur, un message est aussi affiché sur le template pour informer l'utilisateur.

# 09/11/2022 (v0.5)
 - Correction bug (remonté par jlequen) lié à des commandes non envoyées aux serveurs MELCloud.

# 09/11/2022 (v0.4)
 - Ajout de plus d'informations pour le debug lors de l'envoie d'une commande

# 07/11/2022 (v0.3)
 - Ajout de l'affichage de la météo
 - Correction d'un bug Javascript dans la configuration
 - Gère la possibilité de ne pas avoir de données météo disponible.

# 13/10/2022 (v0.2)
 - Modification gestion cron. Passage à un cron journalier pour les informations de la PAC et le cron toutes les 5 minutes est remplacé par une récupération des informations des splits.
 - Correction d'un bug d'affichage qui faisait afficher des points de rafraichissement sur tous les équipements, au lieu de seulement celui sur lequel l'action a été lancé.

# 03/10/2022 (v0.1)
Création du plugin en version bêta.