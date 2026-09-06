<?php

/*
 * The numbers GET /api/contacts needs that are not columns.
 *
 * Nothing outside config/ reads env(), so this lives here rather than in the
 * controller. It is its own file rather than a third key in config/bookings.php
 * because the existing config files are one per concern, and a contacts cap
 * filed under bookings is one nobody finds when they come looking for it.
 */
return [

    /*
     * The most contacts the endpoint will return in one response.
     *
     * The endpoint is deliberately unpaginated: the screen holds the whole list
     * in memory and does its own sorting, grouping and filtering with no round
     * trip, which is what makes it instant and what makes it work with no
     * signal. But an unpaginated endpoint with no stated limit is a bug waiting
     * for the one account that has five thousand rows.
     *
     * So the cap is a ceiling rather than a refusal. A 422, which is what the
     * events span cap does, would leave that account with a dead screen and no
     * way out: the caller sends no parameters, so it cannot ask for less. It
     * gets the first thousand instead, with `truncated` and `total` in the meta
     * block so the client knows it is looking at part of the list.
     *
     * A thousand rather than the two thousand the events cap uses, and the
     * difference is measured rather than guessed: a contact carrying its
     * bookings is a much larger object than an event, and the figure in the
     * report for this prompt is what set this number. It is roughly a quarter
     * of a megabyte of JSON, which is where a phone on a poor connection starts
     * to suffer, and it is more contacts than a full-time artist accumulates in
     * a decade.
     *
     * The ordering is what makes truncation survivable: everyone with work
     * ahead of them sorts above everyone with only history, so a truncated
     * response is the useful end of the list rather than an arbitrary one.
     */
    'max_contacts' => 1000,

];
