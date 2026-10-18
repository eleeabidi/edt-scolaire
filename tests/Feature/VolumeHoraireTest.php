<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T06.1 — volume horaire maximum des enseignants (colonne de la table users).
 */
class VolumeHoraireTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_users_table_has_the_volume_horaire_max_column()
    {
        $this->assertTrue(Schema::hasColumn('users', 'volume_horaire_max'));
    }

    public function test_a_teacher_can_have_a_maximum_weekly_volume()
    {
        $enseignant = User::factory()->enseignant()->create(['volume_horaire_max' => 18]);

        $this->assertSame(18, $enseignant->refresh()->volume_horaire_max);
    }

    public function test_the_volume_is_optional_for_other_roles()
    {
        $this->assertNull(User::factory()->admin()->create()->volume_horaire_max);
    }

    public function test_a_teacher_cannot_change_his_own_volume_from_the_profile()
    {
        $enseignant = User::factory()->enseignant()->create(['volume_horaire_max' => 18]);

        $this->actingAs($enseignant)->patch(route('profile.update'), [
            'nom' => $enseignant->nom,
            'prenom' => $enseignant->prenom,
            'email' => $enseignant->email,
            'volume_horaire_max' => 40,
        ]);

        $this->assertSame(18, $enseignant->refresh()->volume_horaire_max);
    }
}
