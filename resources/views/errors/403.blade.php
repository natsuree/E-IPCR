@extends('errors.layout')
@section('title', "You can't open this page")
@section('message', $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'This page belongs to another role, or the IPCR is outside your scope.')
@section('hint', 'Each role has its own pages, and you can only open IPCRs you are allowed to see. If you think you should have access, contact the Administrator.')
