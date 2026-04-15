# Eloquent Guidelines
- All Models should be in the `app/Models` directory
- All Models should be ungarded by default. adding Model::unguard() to the boot method is a must.
- 100% test coverage for all models is mandatory.

# Style For Eloquent Models
- Models should be named in a singular form.
- Pivots should be named in a plural form and a descriptive name like (adjusments).

