@extends('site.layouts.app')

@section('title', "Contact - Hôtel Evasion")

@section('content')

<x-site::page-hero title="Contact" subtitle="Nous vous aidons à résoudre toute demande" breadcrumb="Contact" />

<section class="luxivo-contact-sec pt-110 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-box text-center mb-50">
                    <h3 class="mb-30">Besoin d'aide pour une réservation en ligne, une question ou plus d'informations ? Écrivez-nous !</h3>
                    <p>Notre équipe se tient à votre disposition pour répondre à toutes vos questions concernant votre séjour à Hôtel Evasion.</p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-5">
                <div class="contact-info-wrapper mb-5 mb-xl-0">
                    <div class="contact-info-box">
                        {{-- Hotel contact details: Configuration → Hôtel (empty ones are hidden). --}}
                        <div class="luxivo-info-box text-center">
                            <div class="content">
                                <h3>ADRESSE :</h3>
                                <p>{{ $hotelInfo->name() }}@if ($hotelInfo->fullAddress()) <br> {{ $hotelInfo->fullAddress() }}@endif</p>
                            </div>
                        </div>
                        @if ($hotelInfo->phones() || $hotelInfo->get('email') || $hotelInfo->get('whatsapp'))
                            <div class="luxivo-info-box text-center">
                                <div class="content">
                                    <h3>CONTACT :</h3>
                                    @foreach ($hotelInfo->phones() as $phone)
                                        <p><a href="{{ $hotelInfo->telHref($phone) }}">{{ $phone }}</a></p>
                                    @endforeach
                                    @if ($hotelInfo->whatsappUrl())
                                        <p><a href="{{ $hotelInfo->whatsappUrl() }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> {{ $hotelInfo->get('whatsapp') }}</a></p>
                                    @endif
                                    @if ($hotelInfo->get('email'))
                                        <p><a href="mailto:{{ $hotelInfo->get('email') }}">{{ $hotelInfo->get('email') }}</a></p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if ($hotelInfo->socials())
                            <div class="luxivo-info-box text-center">
                                <div class="content">
                                    <h3 class="mb-20">RÉSEAUX SOCIAUX :</h3>
                                    <div class="social-box">
                                        @foreach ($hotelInfo->socials() as [$label, $url, $icon])
                                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $label }}"><i class="{{ $icon }}"></i></a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="contact-form-wrapper">
                    <h3 class="mb-50">Remplissez le formulaire pour toute question</h3>
                    <form class="contact-form">
                        <div class="form-gorup">
                            <input type="text" class="form_control" placeholder="Votre nom*" name="name">
                        </div>
                        <div class="form-gorup">
                            <input type="email" class="form_control" placeholder="Votre e-mail*" name="email">
                        </div>
                        <div class="form-gorup">
                            <input type="text" class="form_control" placeholder="Votre téléphone*" name="phone">
                        </div>
                        <div class="form-gorup">
                            <textarea class="form_control" name="message" placeholder="Votre message*" rows="4"></textarea>
                        </div>
                        <div class="form-gorup">
                            <button class="theme-btn style-one" type="button">ENVOYER LE MESSAGE<i class="far fa-arrow-right"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="luxivo-map-sec">
    <div class="map-box mb-5 mb-xl-0">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3971.8717855326395!2d-4.005891588986964!3d5.436436234816749!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xfc1956557970a51%3A0x7aae95327a429d62!2sComplexe%20h%C3%B4telier%20Evasion%20abobo%20plaque%201!5e0!3m2!1sfr!2sci!4v1792402053893!5m2!1sfr!2sci" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
</section>

@endsection
