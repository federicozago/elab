<?php
include("variabili_globali.php");
$dati = [
    "elab" =>
        [
            "id_flusso"=>$id_flussi["elab"],
            "ip_db" => "mysql-01-$mysql_dev_prod.db.indi.local",
            "porta_db" => $porta_db_mysql_8,//mysql nuovo
            "user_db" => "copygraph",
            "pass_db" => "C0pyGr@ph",
            "database_cliente"=> "elab",
            "azioni_elaborazioni" => [
                "target" => [
                    "Genera etichette" => [
                        "endpoint" => "genera_etichette.php",
                    ],
                    "Prenotazione" => [
                        "endpoint" => "Prenota.php",
                        "parametri" => ["d"]
                    ]
                ],
                "massiva" => [
                    "Genera etichette" => [
                        "endpoint" => "genera_etichette.php",
                    ],
                ]
            ],

            "imap_host" => "imaps.aruba.it",
            "imap_porta" => "993",
            "imap_password" => "Password12!",
            "alias_mittente_segnalazione" => "Segnalazioni",
            "indirizzi_destinatari_segnalazioni" => "dev@indi.it",
            "oggetto_standard" => "Segnalazioni  Elab",
            "host_segnalazioni" => "smtps.aruba.it",//queste variabili servono ai programmi su c/connect/energia_locale che usano direttamente la classe Segnalazioni_e_log e non Gestione automatica.
            "porta_host_segnalazioni" => 465,
            "user_host_segnalazioni" => "srv_php@slymail.eu",
            "password_host_segnalazioni" => "Password12!",
            "smtpsecure_segnalazioni" => "ssl",

            //connsessione postale ftp
            [
                "standard"=>[
                    "ftp_poste_user" => "SA-0030295330",
                    "ftp_poste_password" => "Indi_Poste_042021",
                    "ftp_poste_protocollo" => "sftp",
                    "ftp_poste_host" => "mftprod.posteitaliane.it",
                    "ftp_poste_porta" => "2222",
                    "ftp_poste_host_key" => "ssh-rsa 2048 F0Y3IKxA6Bf1XL1SgEXam30ISsoFf2jp0KZjR8gTz4g",
                    "ftp_poste_folder_distinta" => "DISTINTA/",
                    "ftp_poste_folder_esiti" => "ESITI_DISTINTA/",
                ],
                "kpm"=>[
                    "ftp_poste_user" => "K-INDI",
                    "ftp_poste_password" => "yC4yl00B3n8bNy",
                    "ftp_poste_protocollo" => "sftp",
                    "ftp_poste_host" => "ftp-data.kpmsrl.it",
                    "ftp_poste_porta" => "22",
                    "ftp_poste_host_key" => "ssh-rsa 4096 Emfsjvft8wnHkkv/UUsmQwNuAbJ6PrLxUr9c7CeVhas",
                    "ftp_poste_folder_distinta" => "/in/PTOPE_SPEDIZIONI/",
                    "ftp_poste_folder_esiti" => "/in/PTOPE_SPEDIZIONI/accettati/",
                    "ftp_poste_folder_esiti_errati" => "/in/PTOPE_SPEDIZIONI/errati/",
                ]
            ]
        ]
    ];