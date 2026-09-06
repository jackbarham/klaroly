<?php

namespace App\Enums;

/**
 * Who ended an enquiry: the client, or the artist.
 *
 * It has no HasCheckConstraint and no column, in the same way WaitingOn has
 * neither. The side is not stored: it is derived from the LostReason that is,
 * so there is one place a reason's side is written down and nothing to drift
 * away from it.
 *
 * The API sends it alongside lost_reason rather than leaving the client to map
 * the nine values, because the side is a fact about the record and the label
 * beside it is wording. Facts come from the server.
 */
enum EndingSide: string
{
    case Client = 'client';
    case Artist = 'artist';
}
