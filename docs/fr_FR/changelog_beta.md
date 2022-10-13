# TODO
 - Ajouter les PAC air/eau (si vous en possédez une, merci de me contacter)
 - Ajouter la prise en charge des scénarios
 - Ajouter l'affichage de la météo
 - Mettre à jour la gestion du cron
    - Gestion de la fréquence configurable
    - Deux cron, 1 peu fréquent pour la mise à jour des paramètres des équipements. 1 autre pour la mise à jour des états des équipements.
 - Ajouter la possibilité de choisir quel élément du widget afficher ou non (parmi `Scénario`, `Mode`, `Vitesse de ventillation`, `Ailettes horizontales`, `Ailettes verticales`, `Température`, `Météo`)

# 13/10/2022
 - Modification gestion cron. Passage à un cron journalier pour les informations de la PAC et le cron toutes les 5 minutes est remplacé par une récupération des informations des splits.
 - Correction d'un bug d'affichage qui faisait afficher des points de rafraichissement sur tous les équipements, au lieu de seulement celui sur lequel l'action a été lancé.

# 03/10/2022
Création du plugin en version bêta.