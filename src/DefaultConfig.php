<?php

namespace Erebox\TextAdventureEngine;

class DefaultConfig
{
    protected $config = null;

    protected $lang = [
        'it'=> '{
            "inventory_max": 10,
            "intro": [ "BENVENUTO" ],
            "victory": [ "FINE" ],
            "defeat": [ "FINE" ],
            "help": [
                "Per muoversi usa le direzioni cardinali come \'vai a nord\' (abbreviate anche in n,s,e,o). \n",
                "Il comando \'guarda\' permette di avere descrizioni approfondite degli oggetti presenti nell\'ambiente. \n",
                "Alcuni oggetti possono essere raccolti e deposti usando i comandi \'prendi\' e \'posa\' (o lascia). \n",
                "Il comando \'inventario\' (i) mostra una lista degli oggetti posseduti dal personaggio. \n",
                "Ovviamente gli oggetti possono essere manipolati in altri modi più o meno logici; ",
                "una torcia pu\u00F2 essere accesa o spenta (accendi la torcia/spegni la torcia), ",
                "un pulsante pu\u00F2 essere premuto (premi il pulsante), ",
                "un mobile pu\u00F2 essere spostato (sposta il mobile) o ",
                "una porta pu\u00F2 essere aperta o chiusa (apri la porta/chiudi la porta). \n"
            ],		
            "ignore": [
                "a", "agli", "al", "all", "alla", "alle", "allo", "col", "con", "gli",
                "i", "il", "l", "la", "le", "lo", "un", "una", "uno"
            ],
            "direction": {
                "n": [ "nord" ],
                "s": [ "sud" ],
                "e": [ "est" ],
                "o": [ "ovest" ],
                "a": [ "alto", "sali" ],
                "b": [ "basso", "scendi" ]
            },
            "action": {
                "*": {
                    "what": "Cosa devo fare?",
                    "where": "Sei ",
                    "see": "Vedo ",
                    "parser_noverb" : "Non capisco il verbo",
                    "parser_noitem" : "Non capisco la parola",
                    "parser_say" : "Dimmi qualcosa...",
                    "done": "Fatto.",
                    "notseen": "Cosa?",
                    "notable": "Non ci riesci.",
                    "wip": "Non si pu\u00F2."
                },
                "open": {
                    "alias": [ "apri" ],
                    "already": "E\' gi\u00E0 aperto.",
                    "notable": "Non mi sembra particolarmente apribile."
                },
                "close": {
                    "alias": [ "chiudi" ],
                    "already": "E\' gi\u00E0 chiuso.",
                    "notable": "Dato che non si pu\u00F2 aprire, non si pu\u00F2 chiudere"
                },
                "pickup": {
                    "alias": [ "prendi" ]
                },
                "leave": {
                    "alias": [ "lascia", "posa" ]
                },
                "remove": {
                    "alias": [ "uccidi", "ammazza", "attacca", "colpisci" ]
                },
                "eat": {
                    "alias": [ "bevi", "mangia" ]
                },
                "use": {
                    "alias": [ "usa", "adopera" ],
                    "none": "Non succede nulla di particolare.",
                    "notable": "Non puoi."
                },
                "speak": {
                    "alias": [ "parla", "chiacchera", "saluta" ],
                    "none": "Parlare a vanvera non risolve il tuo problema."
                },		
                "inventory": {
                    "alias": [ "?", "inventario", "cosa", "i" ],
                    "got": "Possiedi:",
                    "none": "un bel nulla"
                },
                "look": {
                    "alias": [ "g", "guarda", "x", "esamina" ],
                    "none": "Non noto nulla di particolare."
                },
                "goto": {
                    "alias": [ "cammina", "muovi", "vai" ],
                    "noitem": "Dove? Indica una direzione (N, S, E, O)",
                    "notable": "Non puoi andare di l\u00E0"
                },
        
                "help": {
                    "alias": [ "aiuto" ]
                },
                "exit": {
                    "alias": [ "end", "exit", "basta", "fine", "crepa", "muori" ],
                    "done": "E\' stato bello giocare con te!\n\nFINE"
                },
                "save": {
                    "alias": [ "salva" ]
                },
                "load": {
                    "alias": [ "riprendi" ]
                },
                "swear": {
                    "alias": [ "fottiti", "fanculo" ]
                },
                "on": {
                    "alias": [ "accendi" ]
                },
                "off": {
                    "alias": [ "spegni" ]
                },
                "score": {
                    "alias": [ "punti", "punteggio" ]
                }	
            }			
        }',
        'en'=> '{
            "inventory_max": 10,
            "intro": [ "WELCOME" ],
            "victory": [ "END" ],
            "defeat": [ "END" ],
            "help": [ "HELP" ],		
            "ignore": [ "with", "to", "for", "the", "a", "an" ],
            "direction": {
                "n": [ "north" ],
                "s": [ "south" ],
                "e": [ "est" ],
                "w": [ "west" ],
                "u": [ "up" ],
                "d": [ "down" ]
            },
            "action": {
                "*": {
                    "what": "?",
                    "where": "You are ",
                    "see": "I see ",
                    "parser_noverb" : "Non capisco il verbo",
                    "parser_noitem" : "Non capisco la parola",
                    "parser_say" : "Say me something...",
                    "done": "Done.",
                    "notseen": "What?",
                    "notable": "You are not able.",
                    "wip": "You can\'t."
                },
                "open": {
                    "already": "Already open.",
                    "notable": "Non mi sembra particolarmente apribile."
                },
                "close": {
                    "already": "Already close.",
                    "notable": "Dato che non si pu\u00F2 aprire, non si pu\u00F2 chiudere"
                },
                "pickup": {
                    "alias": [ "take" ]
                },
                "leave": {
                    "alias": [ "drop" ]
                },
                "remove": {
                    "alias": [ "kill" ]
                },
                "eat": {
                    "alias": [ "drink" ]
                },
                "use": {
                    "alias": [ "usa", "adopera" ],
                    "none": "Non succede nulla di particolare.",
                    "notable": "You are not able."
                },
                "speak": {
                    "alias": [ "talk" ],
                    "none": "Parlare a vanvera non risolve il tuo problema."
                },		
                "inventory": {
                    "alias": [ "?", "inventario", "cosa", "i" ],
                    "got": "Possiedi:",
                    "none": "un bel nulla"
                },
                "look": {
                    "alias": [ "x", "examine" ],
                    "none": "Non noto nulla di particolare."
                },
                "goto": {
                    "alias": [ "walk" ],
                    "noitem": "Where? (N, S, E, W)",
                    "notable": "You can\'t go."
                },
        
                "help": {
                    "alias": [ "aiuto" ]
                },
                "exit": {
                    "alias": [ "end", "exit", "die" ],
                    "done": "Happy to play with you!\n\nEND"
                },
                "save": {
                },
                "load": {
                },
                "swear": {
                    "alias": [ "suck", "fuck" ]
                },
                "on": {
                },
                "off": {
                },
                "score": {
                }	
            }			
        }'
    ];

    public function __construct($l = "en")
    {
        $this->config = json_decode($this->lang[$l], true);
    }

    public function get() 
    {
        return $this->config;
    }
}