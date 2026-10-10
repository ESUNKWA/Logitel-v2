@extends('layouts.hotel')

@section('title', "Questions fréquentes - Hôtel Evasion")

@section('content')

<x-page-hero title="FAQ" subtitle="Les questions fréquemment posées par nos clients" breadcrumb="FAQ" />

<section class="luxivo-faq-sec pt-120 pb-95">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="luxivo-content-box mb-25">
                    <h2 class="mb-40">Chambres & réservations</h2>
                    <div class="accordion" id="accordionOne">
                        @foreach([
                            ["Quels sont les horaires d'arrivée et de départ ?", "Chez Hôtel Evasion, l'arrivée est possible à partir de 15h00 et le départ jusqu'à 11h00. Un arrangement peut être trouvé selon la disponibilité des chambres."],
                            ["Comment annuler ma réservation si j'ai réservé directement auprès de l'hôtel ?", "Vous pouvez annuler votre réservation en nous contactant par téléphone ou e-mail, en respectant les conditions indiquées lors de la réservation."],
                            ["Dois-je payer d'avance pour une réservation directe avec l'hôtel ?", "Cela dépend du tarif choisi. Certaines offres nécessitent un acompte, d'autres non ; les conditions sont précisées à la réservation."],
                            ["Y a-t-il des restrictions pour réserver une chambre depuis l'étranger ?", "Aucune restriction particulière. Nos réservations sont ouvertes à tous, où que vous soyez."],
                        ] as $i => $faq)
                        <div class="accordion-card mb-25">
                            <div class="accordion-header">
                                <h5 class="accordion-title" data-bs-toggle="collapse" data-bs-target="#collapse{{ $i + 1 }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                    {{ $faq[0] }}
                                </h5>
                            </div>
                            <div id="collapse{{ $i + 1 }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#accordionOne">
                                <div class="accordion-content"><p>{{ $faq[1] }}</p></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="luxivo-content-box mb-25">
                    <h2 class="mb-40">Paiement et annulation</h2>
                    <div class="accordion" id="accordionTwo">
                        @foreach([
                            ["Ma carte bancaire sera-t-elle débitée avant mon arrivée ?", "Le débit dépend du type de tarif réservé. Les tarifs flexibles sont débités à l'arrivée, les tarifs promotionnels peuvent être prélevés à la réservation."],
                            ["Ai-je besoin de garantir ma réservation avec une carte bancaire ou un dépôt ?", "Oui, une garantie par carte bancaire est généralement demandée pour confirmer votre réservation."],
                            ["Dois-je envoyer les informations de ma carte par internet pour réserver ?", "La réservation en ligne se fait via un formulaire sécurisé ; vous pouvez également réserver par téléphone."],
                        ] as $i => $faq)
                        <div class="accordion-card mb-25">
                            <div class="accordion-header">
                                <h5 class="accordion-title" data-bs-toggle="collapse" data-bs-target="#collapse2-{{ $i + 1 }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                    {{ $faq[0] }}
                                </h5>
                            </div>
                            <div id="collapse2-{{ $i + 1 }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#accordionTwo">
                                <div class="accordion-content"><p>{{ $faq[1] }}</p></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="luxivo-content-box mb-25">
                    <h2 class="mb-40">Questions complémentaires</h2>
                    <div class="accordion" id="accordionThree">
                        @foreach([
                            ["Puis-je arriver tôt ou partir tard ?", "Selon la disponibilité des chambres, un arrangement peut être proposé pour une arrivée anticipée ou un départ tardif."],
                            ["Où puis-je envoyer mes questions ou remarques sur ce site ?", "N'hésitez pas à utiliser notre formulaire de contact pour toute question ou remarque concernant le site."],
                            ["Comment contacter le service client ?", "Vous pouvez nous joindre par téléphone, e-mail ou via le formulaire de la page Contact."],
                        ] as $i => $faq)
                        <div class="accordion-card mb-25">
                            <div class="accordion-header">
                                <h5 class="accordion-title" data-bs-toggle="collapse" data-bs-target="#collapse3-{{ $i + 1 }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                    {{ $faq[0] }}
                                </h5>
                            </div>
                            <div id="collapse3-{{ $i + 1 }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#accordionThree">
                                <div class="accordion-content"><p>{{ $faq[1] }}</p></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
