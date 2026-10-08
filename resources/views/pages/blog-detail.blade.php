@extends('layouts.hotel')

@section('title', "Les secrets d'une hospitalité de luxe réussie - Espace Katioka")

@section('content')

<x-page-hero title="Détails de l'article" subtitle="Une expérience intégrée à notre culture d'entreprise" breadcrumb="Détails d'un article" />

<section class="luxivo-blog-standard-sec pt-120 pb-70">
    <div class="container">
        <div class="row">
            <div class="col-xl-8">
                <div class="blog-details-wrapper">
                    <div class="blog-post-main mb-70">
                        <div class="blog-post-item">
                            <div class="post-thumbnail">
                                <img src="{{ asset('assets/images/stock/gallery-1.jpg') }}" alt="article">
                            </div>
                            <div class="post-content">
                                <div class="post-meta">
                                    <span><i class="far fa-user"></i> Par <a href="#">Admin</a></span>
                                    <span><i class="far fa-tags"></i><a href="#">Hôtellerie</a></span>
                                    <span><i class="far fa-comment"></i><a href="#">Commentaires (03)</a></span>
                                </div>
                                <h4 class="title">Les secrets d'une hospitalité de luxe réussie</h4>
                                <p>Chez Espace Katioka, l'hospitalité ne se limite pas à un lieu confortable : c'est une attention constante portée à chaque détail, du premier contact jusqu'au départ de nos hôtes.</p>
                                <p>Notre équipe s'attache à créer une atmosphère chaleureuse et authentique, en s'appuyant sur des standards de service rigoureux et une connaissance fine des attentes de chaque voyageur, qu'il s'agisse d'un séjour d'affaires ou de loisirs.</p>
                                <blockquote>
                                    <div class="content">
                                        <p>L'hospitalité, c'est l'art de faire sentir à chacun qu'il compte, dès la première minute de son séjour.</p>
                                        <h5>L'équipe Espace Katioka</h5>
                                    </div>
                                </blockquote>
                                <p>De la réception à la restauration, chaque service est pensé pour offrir une expérience fluide et mémorable, à la hauteur de la réputation que nous souhaitons bâtir à Bouaké et au-delà.</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="luxivo-image"><img src="{{ asset('assets/images/stock/about-1.jpg') }}" alt="article"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="luxivo-image"><img src="{{ asset('assets/images/stock/choose-1.jpg') }}" alt="article"></div>
                                    </div>
                                </div>
                                <h3>Nos engagements</h3>
                                <p>Pour garantir une expérience homogène, nous formons régulièrement nos équipes aux meilleures pratiques du secteur et recueillons les retours de nos clients pour progresser en continu.</p>
                                <ul class="check-list style-one mb-40">
                                    <li>1. Un accueil personnalisé dès l'arrivée</li>
                                    <li>2. Une écoute active des besoins de chaque client</li>
                                    <li>3. Un service disponible à toute heure</li>
                                </ul>
                                <p>C'est cette exigence de tous les instants qui fait la différence, et qui donne envie à nos hôtes de revenir.</p>
                            </div>
                        </div>
                        <div class="entry-footer mt-30">
                            <div class="tag-links">
                                <span>Mots-clés :</span>
                                <a href="#">Hôtel</a>
                                <a href="#">Service</a>
                                <a href="#">Luxe</a>
                            </div>
                            <div class="social-share">
                                <span>Partager :</span>
                                <a href="#"><i class="fab fa-facebook-f"></i></a>
                                <a href="#"><i class="fab fa-instagram"></i></a>
                                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="comment-wrapper mb-50">
                        <h3 class="mb-30">Laisser un commentaire</h3>
                        <form autocomplete="off" class="comment-form">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <input type="text" class="form_control" placeholder="Nom" name="name">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <input type="email" class="form_control" placeholder="E-mail" name="email">
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <textarea class="form_control" placeholder="Message" name="message" rows="5" cols="8"></textarea>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <button class="theme-btn style-one" type="button">Envoyer le commentaire</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="sidebar-widget-area mb-20">
                    <div class="sidebar-widget sidebar-search-widget mb-30">
                        <div class="widget-content">
                            <form>
                                <div class="form-group">
                                    <input type="search" class="form_control" placeholder="Rechercher un mot-clé" name="search">
                                    <button class="search-btn" type="button"><i class="far fa-search"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="sidebar-widget sidebar-nav-widget mb-30">
                        <h4 class="widget-title">Catégories</h4>
                        <div class="widget-content">
                            <ul>
                                <li><a href="#">Guide de la ville<span><i class="far fa-arrow-right"></i></span></a></li>
                                <li><a href="#">Voyage d'affaires<span><i class="far fa-arrow-right"></i></span></a></li>
                                <li><a href="#">Nouveautés<span><i class="far fa-arrow-right"></i></span></a></li>
                                <li><a href="#">Astuces & conseils<span><i class="far fa-arrow-right"></i></span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="sidebar-widget sidebar-post-widget mb-40">
                        <h4 class="widget-title">Articles récents</h4>
                        <div class="widget-content">
                            <ul class="recent-post-list">
                                <li class="post-thumbnail-content mb-4">
                                    <img src="{{ asset('assets/images/stock/gallery-3.jpg') }}" alt="article">
                                    <div class="post-title-date">
                                        <span class="posted-on"><a href="#">1 août 2025</a></span>
                                        <h5><a href="{{ route('blog.show') }}">L'expérience de luxe : ce qui rend notre hôtel unique</a></h5>
                                    </div>
                                </li>
                                <li class="post-thumbnail-content mb-4">
                                    <img src="{{ asset('assets/images/stock/gallery-4.jpg') }}" alt="article">
                                    <div class="post-title-date">
                                        <span class="posted-on"><a href="#">4 août 2025</a></span>
                                        <h5><a href="{{ route('blog.show') }}">Le quotidien et le service de notre hôtel</a></h5>
                                    </div>
                                </li>
                                <li class="post-thumbnail-content mb-4">
                                    <img src="{{ asset('assets/images/stock/room-3.jpg') }}" alt="article">
                                    <div class="post-title-date">
                                        <span class="posted-on"><a href="#">7 août 2025</a></span>
                                        <h5><a href="{{ route('blog.show') }}">Les secrets d'une hospitalité de luxe réussie</a></h5>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="sidebar-widget sidebar-tag-widget mb-30">
                        <h4 class="widget-title">Mots-clés populaires</h4>
                        <div class="widget-content">
                            <a href="#">Salle de bain</a>
                            <a href="#">Piscine</a>
                            <a href="#">Hôtel</a>
                            <a href="#">Détente</a>
                            <a href="#">Petit-déjeuner</a>
                            <a href="#">Chambre</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
