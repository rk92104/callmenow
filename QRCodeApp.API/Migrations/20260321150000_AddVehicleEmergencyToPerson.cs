using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace QRCodeApp.API.Migrations
{
    /// <inheritdoc />
    public class AddVehicleEmergencyToPerson : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.AddColumn<string>(
                name: "EmergencyContactPhone",
                table: "Persons",
                type: "nvarchar(20)",
                maxLength: 20,
                nullable: false,
                defaultValue: "");

            migrationBuilder.AddColumn<string>(
                name: "VehicleRegistration",
                table: "Persons",
                type: "nvarchar(20)",
                maxLength: 20,
                nullable: false,
                defaultValue: "");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropColumn(
                name: "EmergencyContactPhone",
                table: "Persons");

            migrationBuilder.DropColumn(
                name: "VehicleRegistration",
                table: "Persons");
        }
    }
}
