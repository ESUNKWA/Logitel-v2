@extends('layouts.hotel')

@section('title', "Awa Traoré - Hôtel Evation")

@section('content')

<x-page-hero title="Détails du membre" subtitle="Découvrez les visages qui font Hôtel Evation" breadcrumb="Détails d'un membre" />

<section class="luxivo-team-details-sec pt-120 pb-100">
    <div class="container">
        <div class="team-details-wrapper">
            <div class="luxivo-team-main">
                <div class="row align-items-center align-items-lg-start">
                    <div class="col-lg-4">
                        <div class="member-image">
                            <img src="{{ asset('assets/images/stock/team-1.jpg') }}" alt="Awa Traoré">
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="member-info">
                            <h4 class="title">Awa Traoré</h4>
                            <span class="position">Directrice Générale</span>
                            <p>Passionnée d'hôtellerie depuis plus de dix ans, Awa veille chaque jour à ce que chaque client d'Hôtel Evation vive une expérience à la hauteur de ses attentes.</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-box mb-30">
                                        <h5>Expérience :</h5>
                                        <span>10 ans</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box mb-30">
                                        <h5>Téléphone :</h5>
                                        <span><a href="tel:+2250000000000">+225 00 00 00 00 00</a></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box mb-30">
                                        <h5>E-mail :</h5>
                                        <span><a href="mailto:contact@espace-katioka.example">contact@espace-katioka.example</a></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box mb-30">
                                        <h5>Adresse :</h5>
                                        <span>Bouaké, Côte d'Ivoire</span>
                                    </div>
                                </div>
                            </div>
                            <div class="social-box">
                                <a href="#"><i class="fab fa-facebook-f"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                                <a href="#"><i class="fab fa-instagram"></i></a>
                                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-90 align-items-center">
                <div class="col-lg-7">
                    <div class="luxivo-content-box pe-xl-5 mb-5 mb-xl-0">
                        <h3 class="mb-15">Parcours professionnel</h3>
                        <p class="mb-15">Awa a débuté sa carrière dans l'hôtellerie internationale avant de rejoindre Hôtel Evation pour y insuffler une hospitalité résolument tournée vers l'excellence et le service personnalisé.</p>
                        <p class="mb-15">Elle supervise aujourd'hui l'ensemble des opérations de l'hôtel, en veillant à ce que chaque équipe partage la même exigence de qualité au service des clients.</p>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="luxivo-skill-list">
                        <div class="luxivo-skill-item mb-35">
                            <div class="progress-title"><h6>Gestion hôtelière<span>90%</span></h6></div>
                            <div class="progress"><div class="progress-bar" style="width: 90%;"></div></div>
                        </div>
                        <div class="luxivo-skill-item mb-35">
                            <div class="progress-title"><h6>Relation client<span>95%</span></h6></div>
                            <div class="progress"><div class="progress-bar" style="width: 95%;"></div></div>
                        </div>
                        <div class="luxivo-skill-item mb-35">
                            <div class="progress-title"><h6>Coordination d'équipe<span>85%</span></h6></div>
                            <div class="progress"><div class="progress-bar" style="width: 85%;"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="luxivo-team-contact gray-color pt-120 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-7">
                <div class="section-title text-center mb-45">
                    <span class="sub-title">Contactez-nous dès maintenant</span>
                    <h2>Discutons pour mieux vous accompagner</h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <form class="contact-form" autocomplete="off">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="text" class="form_control" placeholder="Votre nom" name="name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="email" class="form_control" placeholder="Votre e-mail" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="text" class="form_control" placeholder="Sujet" name="subject">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="text" class="form_control" placeholder="Téléphone" name="phone">
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-group">
                                <textarea class="form_control" name="message" placeholder="Votre message" rows="9"></textarea>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-group text-center">
                                <button class="theme-btn style-one" type="button">ENVOYER<i class="far fa-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection
